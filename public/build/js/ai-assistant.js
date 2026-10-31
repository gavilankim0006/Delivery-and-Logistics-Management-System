/**
 * AI Assistant 3D Floating Bot & Chat Interface
 * Built with Three.js (Procedural geometries, no external GLB required)
 */
(function () {
    'use strict';

    // State
    let scene, camera, renderer, botGroup, headMesh, eyeVisor, thrusterRing;
    let isThinking = false;
    let mouseX = 0, mouseY = 0;
    let currentConversationId = null;

    // DOM Elements
    let widgetContainer, botTrigger, chatWindow, chatMessages, chatInput, sendBtn, closeBtn, typingIndicator;

    function init() {
        createDOM();
        init3D();
        restorePosition();
        initDrag();
        attachEvents();
        loadHistory();
    }

    function createDOM() {
        widgetContainer = document.createElement('div');
        widgetContainer.id = 'ai-assistant-widget';

        widgetContainer.innerHTML = `
            <div class="ai-bot-trigger" id="ai-bot-trigger" title="Chat with AI Assistant">
                <div id="ai-3d-canvas-container" style="width: 100%; height: 100%;"></div>
                <div class="ai-bot-badge"></div>
                <div class="ai-bot-tooltip">Chat with Dispatch AI</div>
            </div>

            <div class="ai-chat-window" id="ai-chat-window">
                <div class="ai-chat-header">
                    <div class="ai-chat-header-title">
                        <span class="ai-header-dot"></span>
                        <div>
                            <h6>Dispatch AI</h6>
                            <span class="ai-chat-role-tag" id="ai-role-tag">Assistant</span>
                        </div>
                    </div>
                    <button type="button" class="ai-chat-close-btn" id="ai-chat-close" aria-label="Close chat">&times;</button>
                </div>
                <div class="ai-chat-messages" id="ai-chat-messages">
                    <div class="ai-msg ai-msg-assistant">
                        Hello! 👋 I am your Dispatch AI assistant. How can I assist you with your logistics, orders, or fleet tasks today?
                    </div>
                    <div class="ai-typing-indicator" id="ai-typing-indicator">
                        <span class="ai-typing-dot"></span>
                        <span class="ai-typing-dot"></span>
                        <span class="ai-typing-dot"></span>
                    </div>
                </div>
                <form class="ai-chat-input-area" id="ai-chat-form">
                    <input type="text" class="ai-chat-input" id="ai-chat-input" placeholder="Ask about orders, rates, tasks..." autocomplete="off">
                    <button type="submit" class="ai-chat-send-btn" id="ai-chat-send" aria-label="Send message">
                        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </form>
            </div>
        `;

        document.body.appendChild(widgetContainer);

        botTrigger = document.getElementById('ai-bot-trigger');
        chatWindow = document.getElementById('ai-chat-window');
        chatMessages = document.getElementById('ai-chat-messages');
        chatInput = document.getElementById('ai-chat-input');
        sendBtn = document.getElementById('ai-chat-send');
        closeBtn = document.getElementById('ai-chat-close');
        typingIndicator = document.getElementById('ai-typing-indicator');
    }

    function init3D() {
        const container = document.getElementById('ai-3d-canvas-container');
        if (!container || typeof THREE === 'undefined') {
            console.warn('Three.js not loaded or canvas container missing.');
            return;
        }

        const width = 80;
        const height = 80;

        scene = new THREE.Scene();
        camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
        camera.position.set(0, 0, 4.2);

        renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        container.appendChild(renderer.domElement);

        // Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.85);
        scene.add(ambientLight);

        const pointLight = new THREE.PointLight(0x087F98, 2.5, 10);
        pointLight.position.set(2, 2, 3);
        scene.add(pointLight);

        const fillLight = new THREE.DirectionalLight(0x4ade80, 0.8);
        fillLight.position.set(-2, -1, 1);
        scene.add(fillLight);

        // 3D Bot Group
        botGroup = new THREE.Group();

        // 1. Robot Head (Smooth White/Gloss Capsule or Sphere)
        const headGeo = new THREE.SphereGeometry(0.9, 32, 32);
        const headMat = new THREE.MeshStandardMaterial({
            color: 0xf8fafc,
            metalness: 0.2,
            roughness: 0.15
        });
        headMesh = new THREE.Mesh(headGeo, headMat);
        botGroup.add(headMesh);

        // 2. Glowing Visor (Eyes / Face plate)
        const visorGeo = new THREE.CylinderGeometry(0.55, 0.55, 0.25, 24, 1, false, -Math.PI / 3, (Math.PI * 2) / 3);
        const visorMat = new THREE.MeshStandardMaterial({
            color: 0x087F98,
            emissive: 0x087F98,
            emissiveIntensity: 0.85,
            metalness: 0.8,
            roughness: 0.2
        });
        eyeVisor = new THREE.Mesh(visorGeo, visorMat);
        eyeVisor.rotation.x = Math.PI / 2;
        eyeVisor.position.set(0, 0.05, 0.72);
        botGroup.add(eyeVisor);

        // 3. Cute Antenna with glowing beacon tip
        const stemGeo = new THREE.CylinderGeometry(0.04, 0.04, 0.45, 12);
        const stemMat = new THREE.MeshStandardMaterial({ color: 0x64748b, metalness: 0.8 });
        const stem = new THREE.Mesh(stemGeo, stemMat);
        stem.position.set(0, 1.1, 0);
        botGroup.add(stem);

        const tipGeo = new THREE.SphereGeometry(0.12, 16, 16);
        const tipMat = new THREE.MeshStandardMaterial({
            color: 0x10b981,
            emissive: 0x10b981,
            emissiveIntensity: 1.2
        });
        const tip = new THREE.Mesh(tipGeo, tipMat);
        tip.position.set(0, 1.35, 0);
        botGroup.add(tip);

        // 4. Hover Thruster Ring
        const ringGeo = new THREE.TorusGeometry(0.65, 0.08, 16, 32);
        const ringMat = new THREE.MeshStandardMaterial({
            color: 0x0ea5e9,
            emissive: 0x0284c7,
            emissiveIntensity: 0.6
        });
        thrusterRing = new THREE.Mesh(ringGeo, ringMat);
        thrusterRing.rotation.x = Math.PI / 2;
        thrusterRing.position.set(0, -0.9, 0);
        botGroup.add(thrusterRing);

        scene.add(botGroup);

        animate();
    }

    let clock = 0;
    function animate() {
        requestAnimationFrame(animate);
        clock += 0.035;

        if (botGroup) {
            // Floating sinusoidal hover
            botGroup.position.y = Math.sin(clock) * 0.12;

            if (isThinking) {
                // Fast curious spin/tilt when thinking
                botGroup.rotation.y += 0.06;
                botGroup.rotation.z = Math.sin(clock * 3) * 0.15;
                if (eyeVisor) eyeVisor.material.emissiveIntensity = 1.0 + Math.sin(clock * 6) * 0.5;
            } else {
                // Smooth tracking user mouse cursor
                const targetRotY = mouseX * 0.5;
                const targetRotX = -mouseY * 0.3;
                botGroup.rotation.y += (targetRotY - botGroup.rotation.y) * 0.08;
                botGroup.rotation.x += (targetRotX - botGroup.rotation.x) * 0.08;
                botGroup.rotation.z = Math.sin(clock * 0.5) * 0.05;
                if (eyeVisor) eyeVisor.material.emissiveIntensity = 0.85 + Math.sin(clock * 2) * 0.2;
            }

            if (thrusterRing) {
                thrusterRing.rotation.z += 0.04;
            }
        }

        renderer.render(scene, camera);
    }

    function restorePosition() {
        try {
            const saved = localStorage.getItem('dispatch_ai_bot_pos');
            if (saved) {
                const pos = JSON.parse(saved);
                const maxLeft = window.innerWidth - 90;
                const maxTop = window.innerHeight - 90;
                const left = Math.max(10, Math.min(maxLeft, pos.left));
                const top = Math.max(10, Math.min(maxTop, pos.top));
                widgetContainer.style.left = `${left}px`;
                widgetContainer.style.top = `${top}px`;
                widgetContainer.style.right = 'auto';
                widgetContainer.style.bottom = 'auto';
                updateChatWindowPosition();
            }
        } catch (e) {}
    }

    function updateChatWindowPosition() {
        if (!chatWindow || !widgetContainer) return;
        const rect = widgetContainer.getBoundingClientRect();
        const chatWidth = Math.min(380, window.innerWidth - 32);
        const chatHeight = Math.min(540, window.innerHeight - 130);

        // Horizontal placement: anchor next to bot without leaving screen
        let left;
        if (rect.left + rect.width / 2 > window.innerWidth / 2) {
            left = rect.right - chatWidth;
        } else {
            left = rect.left;
        }
        left = Math.max(16, Math.min(window.innerWidth - chatWidth - 16, left));

        // Vertical placement: open upwards or downwards depending on space
        let top;
        if (rect.top + rect.height / 2 > window.innerHeight / 2) {
            top = rect.top - chatHeight - 14;
        } else {
            top = rect.bottom + 14;
        }
        top = Math.max(16, Math.min(window.innerHeight - chatHeight - 16, top));

        chatWindow.style.left = `${left}px`;
        chatWindow.style.top = `${top}px`;
        chatWindow.style.right = 'auto';
        chatWindow.style.bottom = 'auto';
    }

    function toggleChat() {
        updateChatWindowPosition();
        const isOpen = chatWindow.classList.toggle('open');
        if (isOpen) {
            chatInput.focus();
        }
    }

    function initDrag() {
        let isDragging = false;
        let hasMoved = false;
        let startX = 0, startY = 0;
        let initialLeft = 0, initialTop = 0;

        botTrigger.addEventListener('pointerdown', (e) => {
            if (e.button && e.button !== 0) return;

            hasMoved = false;
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;

            const rect = widgetContainer.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            botTrigger.setPointerCapture(e.pointerId);
        });

        botTrigger.addEventListener('pointermove', (e) => {
            if (!isDragging) return;

            const dx = e.clientX - startX;
            const dy = e.clientY - startY;

            if (!hasMoved && Math.hypot(dx, dy) > 5) {
                hasMoved = true;
                botTrigger.classList.add('is-dragging');
            }

            if (hasMoved) {
                const maxLeft = window.innerWidth - 90;
                const maxTop = window.innerHeight - 90;
                const newLeft = Math.max(10, Math.min(maxLeft, initialLeft + dx));
                const newTop = Math.max(10, Math.min(maxTop, initialTop + dy));

                widgetContainer.style.left = `${newLeft}px`;
                widgetContainer.style.top = `${newTop}px`;
                widgetContainer.style.right = 'auto';
                widgetContainer.style.bottom = 'auto';

                updateChatWindowPosition();
            }
        });

        function stopDrag(e) {
            if (!isDragging) return;
            isDragging = false;
            botTrigger.classList.remove('is-dragging');

            if (hasMoved) {
                const rect = widgetContainer.getBoundingClientRect();
                try {
                    localStorage.setItem('dispatch_ai_bot_pos', JSON.stringify({
                        left: rect.left,
                        top: rect.top
                    }));
                } catch (err) {}
            } else {
                toggleChat();
            }
        }

        botTrigger.addEventListener('pointerup', stopDrag);
        botTrigger.addEventListener('pointercancel', stopDrag);

        window.addEventListener('resize', () => {
            if (widgetContainer.style.left) {
                const maxLeft = window.innerWidth - 90;
                const maxTop = window.innerHeight - 90;
                const curLeft = parseFloat(widgetContainer.style.left) || 0;
                const curTop = parseFloat(widgetContainer.style.top) || 0;
                widgetContainer.style.left = `${Math.max(10, Math.min(maxLeft, curLeft))}px`;
                widgetContainer.style.top = `${Math.max(10, Math.min(maxTop, curTop))}px`;
            }
            updateChatWindowPosition();
        });
    }

    function attachEvents() {
        closeBtn.addEventListener('click', () => {
            chatWindow.classList.remove('open');
        });

        // Track cursor for 3D head pivot
        window.addEventListener('mousemove', (e) => {
            const x = (e.clientX / window.innerWidth) * 2 - 1;
            const y = -(e.clientY / window.innerHeight) * 2 + 1;
            mouseX = x;
            mouseY = y;
        });

        // Form submit
        const form = document.getElementById('ai-chat-form');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            sendMessage();
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatMarkdown(text) {
        if (!text) return '';
        let escaped = escapeHtml(text);
        // Render common block Markdown as semantic HTML.
        escaped = escaped.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        escaped = escaped.replace(/^## (.+)$/gm, '<h2>$1</h2>');
        escaped = escaped.replace(/^# (.+)$/gm, '<h1>$1</h1>');
        escaped = escaped.replace(/^---+$/gm, '<hr>');
        escaped = escaped.replace(/^\* (.+)$/gm, '• $1');
        // Bold
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italics
        escaped = escaped.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Inline code
        escaped = escaped.replace(/`([^`]+)`/g, '<code>$1</code>');
        // Newlines to line breaks
        escaped = escaped.replace(/\n/g, '<br>');
        return escaped;
    }

    function appendMessage(sender, text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-msg ai-msg-${sender}`;
        msgDiv.innerHTML = sender === 'assistant' ? formatMarkdown(text) : escapeHtml(text);
        chatMessages.insertBefore(msgDiv, typingIndicator);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        appendMessage('user', text);
        chatInput.value = '';
        chatInput.disabled = true;
        sendBtn.disabled = true;

        isThinking = true;
        typingIndicator.classList.add('active');
        chatMessages.scrollTop = chatMessages.scrollHeight;

        try {
            const baseUrl = window.location.origin + (window.location.pathname.startsWith('/SIAproject') ? '/SIAproject' : '');
            const response = await fetch(`${baseUrl}/ai/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: text,
                    conversation_id: currentConversationId
                })
            });

            const responseText = await response.text();
            let data = null;
            try {
                data = JSON.parse(responseText);
            } catch (jsonErr) {
                console.error('AI server returned non-JSON:', responseText);
                appendMessage('assistant', `⚠️ **Server Notice (${response.status})**: ${responseText.replace(/\s+/g, ' ').slice(0, 220) || 'Empty response.'}`);
                return;
            }

            if (data && data.success) {
                currentConversationId = data.conversation_id;
                appendMessage('assistant', data.reply);
                if (data.role) {
                    const tag = document.getElementById('ai-role-tag');
                    if (tag) tag.textContent = data.role.toUpperCase();
                }
            } else {
                appendMessage('assistant', `⚠️ **AI Notice**: ${data && data.error ? data.error : 'Failed to get response.'}`);
            }
        } catch (err) {
            appendMessage('assistant', '⚠️ **Connection Error**: Could not connect to AI assistant service.');
            console.error('AI chat error:', err);
        } finally {
            isThinking = false;
            typingIndicator.classList.remove('active');
            chatInput.disabled = false;
            sendBtn.disabled = false;
            chatInput.focus();
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    async function loadHistory() {
        try {
            const baseUrl = window.location.origin + (window.location.pathname.startsWith('/SIAproject') ? '/SIAproject' : '');
            const res = await fetch(`${baseUrl}/ai/history`);
            const data = await res.json();
            if (data.success && data.messages && data.messages.length > 0) {
                currentConversationId = data.conversation_id;
                // Clear initial placeholder if history exists
                chatMessages.querySelectorAll('.ai-msg').forEach(el => el.remove());
                data.messages.forEach(msg => {
                    appendMessage(msg.sender, msg.content);
                });
            }
        } catch (e) {
            // Silently ignore history fetch on fresh visits
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
