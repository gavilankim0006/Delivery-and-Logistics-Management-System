<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Ai Assistant Controller
 *
 * Handles AJAX chat queries, conversation persistence, tool executions, and state.
 */
class Ai extends Controller
{
    protected $openAi;

    public function __construct()
    {
        parent::__construct();
        // Database autoload can be disabled for health-only requests.
        if (!isset($this->db) || !is_object($this->db)) {
            $this->db = Database::instance(null);
        }
        $this->call->helper('auth');
        $this->openAi = $this->call->library('OpenAiService');
        $this->call->library('AiToolRegistry');
        $this->call->library('api');
    }

    /**
     * POST /ai/chat
     * Accepts JSON: { message: string, conversation_id: ?int }
     */
    public function chat()
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Parse input
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $message = trim((string)($payload['message'] ?? ''));
        if ($message === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Message cannot be empty']);
            return;
        }

        $user = auth_user();
        $role = $user ? (string)($user['role'] ?? 'guest') : 'guest';
        $userId = $user ? (int)$user['id'] : null;
        $sessionId = session_id();

        try {
            // Find or create conversation
            $convId = isset($payload['conversation_id']) && (int)$payload['conversation_id'] > 0
                ? (int)$payload['conversation_id']
                : null;

            if ($convId !== null) {
                $checkStmt = $this->db->raw(
                    'SELECT id FROM ai_conversations WHERE id = ? AND (user_id = ? OR session_id = ?) LIMIT 1',
                    [$convId, $userId, $sessionId]
                );
                if (!$checkStmt || !$checkStmt->fetch()) {
                    $convId = null; // Reset if unauthorized or not found
                }
            }

            if ($convId === null) {
                $this->db->raw(
                    'INSERT INTO ai_conversations (session_id, user_id, role, title, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())',
                    [$sessionId, $userId, $role, mb_substr($message, 0, 50)]
                );
                $convId = (int)$this->db->last_id();
            }

            // Save user message to database
            $this->db->raw(
                'INSERT INTO ai_messages (conversation_id, sender, content, created_at) VALUES (?, "user", ?, NOW())',
                [$convId, $message]
            );

            // Initialize Tool Registry for this user/role
            $registry = new AiToolRegistry($this->db, $role, $userId);
            $tools = $registry->getTools();

            // Load last 10 messages for conversation context
            $histStmt = $this->db->raw(
                'SELECT sender, content, tool_calls, tool_call_id, tool_name FROM ai_messages
                 WHERE conversation_id = ? ORDER BY id DESC LIMIT 10',
                [$convId]
            );
            $rawHistory = $histStmt ? array_reverse($histStmt->fetchAll(PDO::FETCH_ASSOC)) : [];

            $formattedMessages = [];
            $formattedMessages[] = [
                'role' => 'system',
                'content' => $registry->getSystemPrompt()
            ];

            foreach ($rawHistory as $row) {
                // Older stored function calls may lack Gemini thought signatures;
                // omit tool turns from historical context to avoid replaying invalid pairs.
                if (!empty($row['tool_calls']) || !empty($row['tool_call_id']) || $row['sender'] === 'tool') {
                    continue;
                }
                $item = [
                    'role' => $row['sender'],
                    'content' => $row['content']
                ];
                if (!empty($row['tool_calls'])) {
                    $item['tool_calls'] = json_decode($row['tool_calls'], true);
                    $item['_gemini_parts'] = array_values(array_filter(array_map(
                        static fn($call) => $call['_gemini_part'] ?? null,
                        is_array($item['tool_calls']) ? $item['tool_calls'] : []
                    )));
                }
                if (!empty($row['tool_call_id'])) {
                    $item['tool_call_id'] = $row['tool_call_id'];
                }
                if (!empty($row['tool_name'])) {
                    $item['name'] = $row['tool_name'];
                }
                $formattedMessages[] = $item;
            }

            // Call Gemini (First Pass)
            $response = $this->openAi->chatCompletion($formattedMessages, $tools);
            $choice = $response['choices'][0]['message'] ?? null;

            if (!$choice) {
                throw new RuntimeException('Empty response from AI engine');
            }

            // Check if model wants to call tools
            if (!empty($choice['tool_calls'])) {
                // Record assistant tool-intent message
                $this->db->raw(
                    'INSERT INTO ai_messages (conversation_id, sender, content, tool_calls, created_at) VALUES (?, "assistant", ?, ?, NOW())',
                    [$convId, (string)($choice['content'] ?? ''), json_encode($choice['tool_calls'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
                );

                $formattedMessages[] = $choice;

                // Execute each tool and append results
                foreach ($choice['tool_calls'] as $tCall) {
                    $fnName = $tCall['function']['name'] ?? '';
                    $fnArgs = json_decode($tCall['function']['arguments'] ?? '{}', true) ?: [];
                    $toolOutput = $registry->execute($fnName, $fnArgs);

                    $outputStr = json_encode($toolOutput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    $this->db->raw(
                        'INSERT INTO ai_messages (conversation_id, sender, content, tool_call_id, tool_name, created_at) VALUES (?, "tool", ?, ?, ?, NOW())',
                        [$convId, $outputStr, $tCall['id'], $fnName]
                    );

                    $formattedMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $tCall['id'],
                        'name' => $fnName,
                        'content' => $outputStr
                    ];
                }

                // Second Pass: Call OpenAI with tool execution results
                $secondResponse = $this->openAi->chatCompletion($formattedMessages, []);
                $choice = $secondResponse['choices'][0]['message'] ?? [
                    'role' => 'assistant',
                    'content' => 'I have processed your request with the system tools.'
                ];
            }

            $assistantReply = (string)($choice['content'] ?? '');

            // Save final assistant message
            $this->db->raw(
                'INSERT INTO ai_messages (conversation_id, sender, content, created_at) VALUES (?, "assistant", ?, NOW())',
                [$convId, $assistantReply]
            );

            echo json_encode([
                'success' => true,
                'conversation_id' => $convId,
                'reply' => $assistantReply,
                'role' => $role,
                'model' => $this->openAi->getModel(),
                'is_mock' => !$this->openAi->isConfigured()
            ]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /ai/history?conversation_id=X
     */
    public function history()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $user = auth_user();
        $userId = $user ? (int)$user['id'] : null;
        $sessionId = session_id();

        $convId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;

        if ($convId === null) {
            // Find most recent conversation for this session/user
            $findStmt = $this->db->raw(
                'SELECT id FROM ai_conversations WHERE user_id = ? OR session_id = ? ORDER BY id DESC LIMIT 1',
                [$userId, $sessionId]
            );
            $row = $findStmt ? $findStmt->fetch(PDO::FETCH_ASSOC) : null;
            $convId = $row ? (int)$row['id'] : null;
        }

        if ($convId === null) {
            echo json_encode([
                'success' => true,
                'conversation_id' => null,
                'messages' => []
            ]);
            return;
        }

        $stmt = $this->db->raw(
            'SELECT sender, content, created_at FROM ai_messages
             WHERE conversation_id = ? AND sender IN ("user", "assistant")
             ORDER BY id ASC LIMIT 50',
            [$convId]
        );
        $messages = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        echo json_encode([
            'success' => true,
            'conversation_id' => $convId,
            'messages' => $messages
        ]);
    }
}
