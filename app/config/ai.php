<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| AI Assistant Configuration
|--------------------------------------------------------------------------
|
| Configuration for AI chat, OpenRouter, and Gemini integration.
|
*/
$config['ors_api_key'] = getenv('ORS_API_KEY') ?: '';
$config['gemini_api_keys'] = getenv('GEMINI_API_KEYS') ?: '';
$config['openai_api_key'] = getenv('OPENAI_API_KEY') ?: '';
$config['ai_model'] = getenv('GEMINI_MODEL') ?: (getenv('AI_MODEL') ?: 'gemini-3.5-flash');
