/**
 * DevDojo Bridge — builder side.
 *
 * The canvas iframes the target app, which runs the app side of the bridge
 * (see the app's resources/js/app.js). The two sides speak a tiny versioned
 * postMessage protocol, origin-checked in both directions:
 *
 *   builder → app : hello, navigate {path}, reload, theme {mode}
 *   app → builder : ready {path,title,theme}, state {path,title,theme}
 *
 * If the app never answers (bridge script missing or blocked), the canvas
 * still works as a plain preview — the chrome just reports "Preview only"
 * and falls back to driving the iframe's src directly.
 */
const BRIDGE_SOURCE = 'devdojo-bridge';
const BRIDGE_VERSION = 1;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('builder', ({ appUrl, projectName }) => ({
        appUrl,
        projectName,
        appOrigin: new URL(appUrl).origin,
        appHost: new URL(appUrl).host,

        status: 'connecting', // connecting | bridge | preview
        loaded: false,
        path: '/',
        pathDraft: '/',
        previewTheme: 'light',
        device: 'desktop',

        history: ['/'],
        historyIndex: 0,
        suppressHistory: false,

        target: null,
        copied: false,

        helloTimer: null,

        get statusLabel() {
            return { bridge: 'Connected', preview: 'Preview only', connecting: 'Connecting' }[this.status];
        },

        get canBack() {
            return this.historyIndex > 0;
        },

        get canForward() {
            return this.historyIndex < this.history.length - 1;
        },

        get deviceWidth() {
            return { desktop: '100%', tablet: '834px', phone: '390px' }[this.device];
        },

        init() {
            window.addEventListener('message', (event) => {
                if (event.origin !== this.appOrigin) {
                    return;
                }

                const message = event.data;

                if (! message || message.source !== BRIDGE_SOURCE || message.v !== BRIDGE_VERSION) {
                    return;
                }

                if (message.type === 'ready' || message.type === 'state') {
                    this.onAppState(message.payload ?? {});
                }
            });

            this.startHandshake();
        },

        // Ping the canvas until the app's bridge answers; the app also
        // announces itself on load, so whichever side is ready last still
        // completes the handshake.
        startHandshake() {
            let attempts = 0;

            clearInterval(this.helloTimer);

            this.helloTimer = setInterval(() => {
                if (this.status === 'bridge' || ++attempts > 20) {
                    clearInterval(this.helloTimer);
                    return;
                }

                this.sendToCanvas('hello');
            }, 300);
        },

        onCanvasLoad() {
            this.loaded = true;

            if (this.status !== 'bridge') {
                // Give the app's bridge a moment to answer before settling
                // for a plain preview.
                setTimeout(() => {
                    if (this.status === 'connecting') {
                        this.status = 'preview';
                    }
                }, 2500);
            }
        },

        onAppState({ path, theme }) {
            this.status = 'bridge';
            this.loaded = true;

            if (typeof theme === 'string') {
                this.previewTheme = theme;
            }

            if (typeof path !== 'string') {
                return;
            }

            this.path = path;
            this.pathDraft = path;

            if (this.suppressHistory) {
                this.suppressHistory = false;
            } else if (this.history[this.historyIndex] !== path) {
                this.history = this.history.slice(0, this.historyIndex + 1).concat(path);
                this.historyIndex = this.history.length - 1;
            }
        },

        sendToCanvas(type, payload = {}) {
            this.$refs.canvas.contentWindow?.postMessage(
                { source: BRIDGE_SOURCE, v: BRIDGE_VERSION, type, payload },
                this.appOrigin
            );
        },

        navigate(path) {
            if (! path.startsWith('/')) {
                path = '/' + path;
            }

            if (this.status === 'bridge') {
                this.sendToCanvas('navigate', { path });
            } else {
                this.loaded = false;
                this.$refs.canvas.src = this.appUrl + path;
                this.startHandshake();
            }
        },

        commitPath() {
            this.navigate(this.pathDraft.trim() || '/');
        },

        back() {
            if (! this.canBack) {
                return;
            }

            this.historyIndex--;
            this.suppressHistory = true;
            this.navigate(this.history[this.historyIndex]);
        },

        forward() {
            if (! this.canForward) {
                return;
            }

            this.historyIndex++;
            this.suppressHistory = true;
            this.navigate(this.history[this.historyIndex]);
        },

        reload() {
            if (this.status === 'bridge') {
                this.suppressHistory = true;
                this.sendToCanvas('reload');
            } else {
                this.loaded = false;
                this.$refs.canvas.src = this.appUrl + this.path;
                this.startHandshake();
            }
        },

        openExternal() {
            window.open(this.appUrl + this.path, '_blank');
        },

        setDevice(device) {
            this.device = device;
        },

        togglePreviewTheme() {
            const mode = this.previewTheme === 'dark' ? 'light' : 'dark';

            this.previewTheme = mode;
            this.sendToCanvas('theme', { mode });
        },

        async share() {
            try {
                await navigator.clipboard.writeText(this.appUrl + this.path);
                this.copied = true;
                setTimeout(() => (this.copied = false), 1600);
            } catch {
                window.prompt('Copy your app link:', this.appUrl + this.path);
            }
        },

        async fetchTarget() {
            if (this.target) {
                return;
            }

            try {
                const response = await fetch('/api/target-status', { headers: { Accept: 'application/json' } });
                this.target = await response.json();
            } catch {
                this.target = { reachable: false };
            }
        },
    }));
});
