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
    let ignoreNextClick = false;
    let viewportSyncFrame = 0;
    let mobileMediaQuery = null;
    let lastMobileViewport = null;

    const DESKTOP_INPUT_PLACEHOLDER = 'Ask about orders, rates, tasks... (Enter to send, Shift+Enter for new line)';
    const MOBILE_INPUT_PLACEHOLDER = 'Ask Dispatch AI anything...';

    // DOM Elements
    let widgetContainer, botTrigger, chatWindow, chatMessages, chatInput, sendBtn, closeBtn, typingIndicator;

    function isMobileViewport() {
        if (!mobileMediaQuery) {
            mobileMediaQuery = window.matchMedia('(max-width: 767.98px)');
        }
        return mobileMediaQuery.matches;
    }

    function init() {
        // If on dedicated Package Messenger page, do not mount floating bot widget
        // as the messenger includes its own integrated Dispatch AI Assistant chat & panel
        if (
            document.getElementById('messenger-vue-root') ||
            document.querySelector('.messenger-wrapper') ||
            window.location.pathname.includes('package-workflow')
        ) {
            return;
        }

        createDOM();
        trackBottomBars();
        lastMobileViewport = isMobileViewport();
        updateComposerPlaceholder();
        init3D();
        restorePosition();
        initDrag();
        attachEvents();
        loadHistory();
    }

    function createDOM() {
        widgetContainer = document.createElement('div');
        widgetContainer.id = 'ai-assistant-widget';
        if (document.querySelector('.dispatch-page-shell')) {
            widgetContainer.classList.add('dispatch-assistant-pinned');
        }

        widgetContainer.innerHTML = `
            <div class="ai-bot-trigger" id="ai-bot-trigger" title="Chat with AI Assistant" role="button" tabindex="0" aria-label="Open Dispatch AI chat" aria-expanded="false">
                <div id="ai-3d-canvas-container" style="width: 100%; height: 100%;"></div>
                <span class="ai-bot-fallback" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="7" width="16" height="12" rx="4"></rect>
                        <path d="M12 3v4"></path>
                        <circle cx="12" cy="3" r="1.2" fill="currentColor" stroke="none"></circle>
                        <circle cx="9" cy="13" r="1.2" fill="currentColor" stroke="none"></circle>
                        <circle cx="15" cy="13" r="1.2" fill="currentColor" stroke="none"></circle>
                        <path d="M9.5 16.5h5"></path>
                    </svg>
                </span>
                <div class="ai-bot-badge"></div>
                <div class="ai-bot-tooltip">Chat with Dispatch AI</div>
            </div>
        `;

        chatWindow = document.createElement('div');
        chatWindow.className = 'ai-chat-window';
        chatWindow.id = 'ai-chat-window';
        chatWindow.setAttribute('role', 'dialog');
        chatWindow.setAttribute('aria-label', 'Dispatch AI chat');
        chatWindow.setAttribute('aria-hidden', 'true');
        chatWindow.inert = true;
        chatWindow.innerHTML = `
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
                <form class="ai-chat-input-area" id="ai-chat-form" data-no-validate="true">
                    <textarea class="ai-chat-input" id="ai-chat-input" placeholder="Ask about orders, rates, tasks... (Enter to send, Shift+Enter for new line)" autocomplete="off" rows="1" data-no-validate="true"></textarea>
                    <button type="submit" class="ai-chat-send-btn" id="ai-chat-send" aria-label="Send message" disabled>
                        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </form>
        `;

        document.body.appendChild(widgetContainer);
        document.body.appendChild(chatWindow);

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
            console.warn('Three.js not loaded or canvas container missing. Using the static AI fallback.');
            return;
        }

        const width = container.clientWidth || (isMobileViewport() ? 64 : 80);
        const height = container.clientHeight || (isMobileViewport() ? 64 : 80);

        scene = new THREE.Scene();
        camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
        camera.position.set(0, 0, 4.2);

        try {
            renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
        } catch (error) {
            console.warn('WebGL is unavailable for the AI assistant. Using the static fallback.', error);
            return;
        }
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.domElement.setAttribute('aria-hidden', 'true');
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

        if (botTrigger) {
            botTrigger.classList.add('has-3d');
        }

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

    let botLeft = 0;
    let botTop = 0;

    function botSize() {
        return isMobileViewport() ? 64 : 80;
    }

    function updateComposerPlaceholder() {
        if (!chatInput) return;
        chatInput.placeholder = isMobileViewport()
            ? MOBILE_INPUT_PLACEHOLDER
            : DESKTOP_INPUT_PLACEHOLDER;
    }

    let safeAreaProbe = null;

    // Reads the bottom safe-area inset (home indicator) via a hidden probe element.
    function safeAreaBottom() {
        if (!safeAreaProbe || !safeAreaProbe.isConnected) {
            safeAreaProbe = document.createElement('div');
            safeAreaProbe.setAttribute('aria-hidden', 'true');
            safeAreaProbe.style.cssText = 'position:fixed;left:0;bottom:0;width:0;height:env(safe-area-inset-bottom, 0px);pointer-events:none;visibility:hidden;';
            document.body.appendChild(safeAreaProbe);
        }
        return safeAreaProbe.getBoundingClientRect().height || 0;
    }

    // Fixed bottom bars the launcher must stay clear of on phones.
    const BOTTOM_BAR_SELECTORS = [
        '.customer-bottom-nav',
        '.dispatch-mobile-bottom-bar',
        '.rider-sticky-bar',
        '.order-form-mobile-bar'
    ];

    let bottomBarOffset = 16;
    let bottomBarResizeObserver = null;
    let bottomBarMutationObserver = null;

    // Fallback for customer pages when the nav element is not (yet) in the DOM.
    function configuredCustomerNavHeight() {
        if (!document.body) return 0;
        if (
            !document.body.classList.contains('has-customer-bottom-nav') &&
            !document.body.classList.contains('role-customer')
        ) {
            return 0;
        }
        const raw = window.getComputedStyle(document.documentElement).getPropertyValue('--customer-bottom-nav-height');
        const configured = parseFloat(raw);
        return (Number.isFinite(configured) && configured > 0 ? configured : 62) + safeAreaBottom();
    }

    // Space that visible bottom bars occupy from the viewport bottom upwards.
    // Using each bar's top edge also covers stacked bars (e.g. an order summary
    // bar rendered above the customer navigation).
    function visibleBottomBarHeight() {
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;
        let reserved = 0;

        BOTTOM_BAR_SELECTORS.forEach((selector) => {
            document.querySelectorAll(selector).forEach((el) => {
                if (el.closest('#ai-assistant-widget') || el.closest('#ai-chat-window')) return;
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return;
                const rect = el.getBoundingClientRect();
                if (rect.height < 24 || rect.height > 200) return;
                if (rect.width < viewportWidth * 0.5) return;
                if (rect.top < 0 || rect.top > viewportHeight) return;
                reserved = Math.max(reserved, viewportHeight - rect.top);
            });
        });

        return reserved > 0 ? reserved : configuredCustomerNavHeight();
    }

    // Distance from the viewport bottom to the launcher's resting bottom edge.
    function refreshBottomBarOffset() {
        if (!isMobileViewport()) {
            bottomBarOffset = 0;
            document.documentElement.style.removeProperty('--ai-bottom-offset');
            return bottomBarOffset;
        }

        const barHeight = visibleBottomBarHeight();
        bottomBarOffset = barHeight > 0 ? barHeight + 12 : safeAreaBottom() + 16;
        document.documentElement.style.setProperty('--ai-bottom-offset', `${bottomBarOffset}px`);
        return bottomBarOffset;
    }

    function observeBottomBars() {
        if (!bottomBarResizeObserver) return;
        BOTTOM_BAR_SELECTORS.forEach((selector) => {
            document.querySelectorAll(selector).forEach((el) => bottomBarResizeObserver.observe(el));
        });
    }

    function handleBottomBarsChanged() {
        observeBottomBars();
        const previous = bottomBarOffset;
        refreshBottomBarOffset();
        if (bottomBarOffset !== previous && hasCustomBotPosition()) {
            if (!botTrigger || !botTrigger.classList.contains('is-dragging')) {
                setBotPosition(botLeft, botTop);
            }
        }
    }

    // Bottom bars can mount after the widget (Vue app shell) or change height
    // (e.g. badge counts), so keep re-measuring them.
    function trackBottomBars() {
        if (typeof ResizeObserver === 'function') {
            bottomBarResizeObserver = new ResizeObserver(() => handleBottomBarsChanged());
            observeBottomBars();
        }

        if (typeof MutationObserver === 'function' && document.body) {
            bottomBarMutationObserver = new MutationObserver(() => handleBottomBarsChanged());
            bottomBarMutationObserver.observe(document.body, { childList: true, subtree: true });
        }

        refreshBottomBarOffset();
    }

    function clampBotPosition(left, top) {
        const size = botSize();
        const margin = isMobileViewport() ? 12 : 24;
        const maxLeft = Math.max(margin, window.innerWidth - size - margin);
        const reservedBottom = isMobileViewport() ? bottomBarOffset : margin;
        const maxTop = Math.max(margin, window.innerHeight - size - reservedBottom);
        return {
            left: Math.max(margin, Math.min(maxLeft, left)),
            top: Math.max(margin, Math.min(maxTop, top))
        };
    }

    function clearInlineBotPosition() {
        if (!widgetContainer) return;
        widgetContainer.style.removeProperty('position');
        widgetContainer.style.removeProperty('left');
        widgetContainer.style.removeProperty('top');
        widgetContainer.style.removeProperty('right');
        widgetContainer.style.removeProperty('bottom');
        widgetContainer.style.removeProperty('transform');
    }

    function hasCustomBotPosition() {
        return Boolean(widgetContainer && widgetContainer.style.getPropertyValue('left'));
    }

    // Records where the CSS-pinned launcher currently sits so drag offsets start correctly.
    function syncPinnedBotCoords() {
        if (!widgetContainer) return;
        const rect = widgetContainer.getBoundingClientRect();
        botLeft = rect.left;
        botTop = rect.top;
    }

    function setBotPosition(left, top) {
        if (!widgetContainer) return;

        const position = clampBotPosition(left, top);
        botLeft = position.left;
        botTop = position.top;

        if (isMobileViewport()) {
            // Inline styles use the "important" priority so a dragged launcher
            // overrides the default CSS pinning above the bottom navigation.
            widgetContainer.style.setProperty('position', 'fixed', 'important');
            widgetContainer.style.setProperty('left', `${botLeft}px`, 'important');
            widgetContainer.style.setProperty('top', `${botTop}px`, 'important');
            widgetContainer.style.setProperty('right', 'auto', 'important');
            widgetContainer.style.setProperty('bottom', 'auto', 'important');
            widgetContainer.style.setProperty('transform', 'none', 'important');
            return;
        }

        widgetContainer.style.position = 'fixed';
        widgetContainer.style.left = `${botLeft}px`;
        widgetContainer.style.top = `${botTop}px`;
        widgetContainer.style.right = 'auto';
        widgetContainer.style.bottom = 'auto';
        widgetContainer.style.transform = 'none';
    }

    function botStorageKey() {
        return isMobileViewport() ? 'dispatch_ai_bot_pos_mobile' : 'dispatch_ai_bot_pos';
    }

    function readSavedBotPosition() {
        try {
            const saved = localStorage.getItem(botStorageKey());
            if (!saved) return null;
            const candidate = JSON.parse(saved);
            if (Number.isFinite(candidate.left) && Number.isFinite(candidate.top)) {
                return candidate;
            }
        } catch (e) {}
        return null;
    }

    function restorePosition() {
        const saved = readSavedBotPosition();

        if (isMobileViewport()) {
            // Keep the default CSS pin unless the user previously dragged it.
            if (saved) {
                setBotPosition(saved.left, saved.top);
            } else {
                syncPinnedBotCoords();
            }
            return;
        }

        const fallback = {
            left: window.innerWidth - botSize() - 24,
            top: window.innerHeight - botSize() - 24
        };
        const position = saved || fallback;
        setBotPosition(position.left, position.top);
    }

    function clearChatWindowPosition() {
        if (!chatWindow) return;
        ['left', 'top', 'right', 'bottom'].forEach((property) => {
            chatWindow.style.removeProperty(property);
        });
    }

    function updateChatWindowPosition() {
        if (!chatWindow || !widgetContainer) return;

        if (isMobileViewport()) {
            clearChatWindowPosition();
            return;
        }

        const size = botSize();
        const rect = { left: botLeft, top: botTop, right: botLeft + size, bottom: botTop + size, width: size, height: size };
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

    // Keep the full-screen mobile chat aligned with the visual viewport so the
    // composer stays visible when the on-screen keyboard opens.
    function syncMobileChatViewport() {
        if (!chatWindow) return;

        const shouldTrack = isMobileViewport() && chatWindow.classList.contains('open');
        if (!shouldTrack) {
            chatWindow.style.removeProperty('--ai-chat-top');
            chatWindow.style.removeProperty('--ai-chat-height');
            return;
        }

        const viewport = window.visualViewport;
        const height = viewport ? viewport.height : window.innerHeight;
        const top = viewport ? viewport.offsetTop : 0;

        chatWindow.style.setProperty('--ai-chat-top', `${Math.max(0, top)}px`);
        chatWindow.style.setProperty('--ai-chat-height', `${Math.max(240, height)}px`);

        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    function scheduleViewportSync() {
        if (viewportSyncFrame) return;
        viewportSyncFrame = window.requestAnimationFrame(() => {
            viewportSyncFrame = 0;
            syncMobileChatViewport();
        });
    }

    function setChatOpen(open) {
        if (!chatWindow) return;

        const willOpen = Boolean(open);
        if (willOpen === chatWindow.classList.contains('open')) return;
        const hadFocus = chatWindow.contains(document.activeElement);

        if (isMobileViewport()) {
            clearChatWindowPosition();
        } else if (willOpen) {
            updateChatWindowPosition();
        }

        chatWindow.classList.toggle('open', willOpen);
        chatWindow.setAttribute('aria-hidden', String(!willOpen));
        chatWindow.inert = !willOpen;
        if (botTrigger) {
            botTrigger.setAttribute('aria-expanded', String(willOpen));
        }
        document.body.classList.toggle('ai-chat-open', willOpen && isMobileViewport());

        if (willOpen) {
            syncMobileChatViewport();
            if (chatInput) {
                chatInput.focus({ preventScroll: true });
            }
            window.requestAnimationFrame(() => {
                if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
            });
        } else {
            syncMobileChatViewport();
            if (hadFocus && botTrigger) {
                botTrigger.focus({ preventScroll: true });
            }
        }
    }

    function toggleChat() {
        if (!chatWindow) return;
        setChatOpen(!chatWindow.classList.contains('open'));
    }

    function initDrag() {
        let isDragging = false;
        let hasMoved = false;
        let startX = 0, startY = 0;
        let initialLeft = 0, initialTop = 0;
        let pointerX = 0, pointerY = 0;
        let frameId = 0;

        function renderDrag() {
            frameId = 0;
            if (!isDragging || !hasMoved) return;
            const dx = pointerX - startX;
            const dy = pointerY - startY;
            setBotPosition(initialLeft + dx, initialTop + dy);
            if (chatWindow.classList.contains('open')) updateChatWindowPosition();
        }

        botTrigger.addEventListener('pointerdown', (e) => {
            if (e.button && e.button !== 0) return;
            hasMoved = false;
            isDragging = true;
            startX = pointerX = e.clientX;
            startY = pointerY = e.clientY;
            // Read the live box so drags also start correctly from the CSS-pinned spot.
            const rect = widgetContainer.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;
            botTrigger.setPointerCapture(e.pointerId);
        });

        botTrigger.addEventListener('pointermove', (e) => {
            if (!isDragging) return;
            pointerX = e.clientX;
            pointerY = e.clientY;
            if (!hasMoved && Math.hypot(pointerX - startX, pointerY - startY) > 5) {
                hasMoved = true;
                botTrigger.classList.add('is-dragging');
            }
            if (hasMoved && !frameId) frameId = requestAnimationFrame(renderDrag);
        });

        function stopDrag() {
            if (!isDragging) return;
            isDragging = false;
            if (frameId) {
                cancelAnimationFrame(frameId);
                frameId = 0;
            }
            if (hasMoved) {
                const dx = pointerX - startX;
                const dy = pointerY - startY;
                setBotPosition(initialLeft + dx, initialTop + dy);
            }
            botTrigger.classList.remove('is-dragging');
            if (hasMoved) {
                hasMoved = false;
                ignoreNextClick = true;
                window.setTimeout(() => { ignoreNextClick = false; }, 400);
                try {
                    localStorage.setItem(botStorageKey(), JSON.stringify({ left: botLeft, top: botTop }));
                } catch (err) {}
                updateChatWindowPosition();
            }
        }

        function cancelDrag() {
            if (!isDragging) return;
            isDragging = false;
            hasMoved = false;
            if (frameId) {
                cancelAnimationFrame(frameId);
                frameId = 0;
            }
            botTrigger.classList.remove('is-dragging');
            ignoreNextClick = true;
            window.setTimeout(() => { ignoreNextClick = false; }, 400);
        }

        botTrigger.addEventListener('pointerup', stopDrag);
        botTrigger.addEventListener('pointercancel', cancelDrag);

        botTrigger.addEventListener('click', () => {
            if (ignoreNextClick) {
                ignoreNextClick = false;
                return;
            }
            toggleChat();
        });
    }

    function handleViewportResize() {
        const mobile = isMobileViewport();
        updateComposerPlaceholder();
        refreshBottomBarOffset();

        if (mobile) {
            if (lastMobileViewport !== true) {
                // Crossing from desktop: return to the default pinned spot.
                clearInlineBotPosition();
                syncPinnedBotCoords();
            } else if (hasCustomBotPosition()) {
                // Rotated/resized while on a phone: keep the dragged spot, re-clamped.
                setBotPosition(botLeft, botTop);
            } else {
                syncPinnedBotCoords();
            }
            clearChatWindowPosition();
        } else {
            if (lastMobileViewport === true) {
                setBotPosition(window.innerWidth - botSize() - 24, window.innerHeight - botSize() - 24);
            } else {
                setBotPosition(botLeft, botTop);
            }
            if (chatWindow && chatWindow.classList.contains('open')) {
                updateChatWindowPosition();
            }
        }

        if (chatWindow) {
            document.body.classList.toggle('ai-chat-open', mobile && chatWindow.classList.contains('open'));
        }

        lastMobileViewport = mobile;
        scheduleViewportSync();
    }

    function attachEvents() {
        closeBtn.addEventListener('click', () => {
            setChatOpen(false);
        });

        // Keyboard access for the launcher and dialog
        botTrigger.addEventListener('keydown', (e) => {
            if (e.repeat) return;
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                toggleChat();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && chatWindow.classList.contains('open')) {
                e.preventDefault();
                setChatOpen(false);
            }
        });

        // Keep the mobile sheet above the on-screen keyboard.
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleViewportSync);
            window.visualViewport.addEventListener('scroll', scheduleViewportSync);
        }

        // Reposition when crossing the desktop/mobile breakpoint.
        isMobileViewport();
        if (mobileMediaQuery) {
            if (typeof mobileMediaQuery.addEventListener === 'function') {
                mobileMediaQuery.addEventListener('change', handleViewportResize);
            } else if (typeof mobileMediaQuery.addListener === 'function') {
                mobileMediaQuery.addListener(handleViewportResize);
            }
        }

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

        // Chat input events: auto-grow, Enter sends, Shift+Enter new line, disable send when empty
        if (chatInput) {
            chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            chatInput.addEventListener('input', () => {
                chatInput.style.height = '36px';
                chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
                if (sendBtn) {
                    sendBtn.disabled = chatInput.value.trim().length === 0 || isThinking;
                }
            });
        }
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
        if (!text || isThinking) return;

        appendMessage('user', text);
        chatInput.value = '';
        chatInput.style.height = '36px';
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
            sendBtn.disabled = chatInput.value.trim().length === 0;
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
