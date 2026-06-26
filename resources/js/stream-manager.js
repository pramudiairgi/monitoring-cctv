import Hls from "hls.js";

const RECONNECT_DELAYS = [2000, 4000, 8000, 15000, 30000];
const RECONNECT_MAX = 5;
const STALE_TIMEOUT = 20000;

export default class StreamManager {
    constructor(cameraId, videoElement, streamUrl, telemetry, onOffline) {
        this.cameraId = cameraId;
        this.video = videoElement;
        this.streamUrl = streamUrl;
        this.telemetry = telemetry || null;
        this._onOffline = onOffline || null;
        this.hls = null;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
        this.destroyed = false;
        this.startTime = 0;
        this.bufferingStart = 0;
        this.currentBitrate = 0;
        this.currentLevel = -1;
        this._lastFragTime = 0;
        this._staleTimer = null;
        this._subPlaylistRetries = 0;
        this._suspended = false;
        this.placeholder = this.video
            ?.closest(".camera-cell")
            ?.querySelector(".camera-placeholder");
        if (this.video) {
            this.video.style.transform = "translateZ(0)";
        }
    }

    getConfig() {
        return {
            enableWorker: true,
            lowLatencyMode: false,
            useFetch: false,
            liveSyncDuration: 40,
            liveMaxLatencyDuration: 50,
            maxBufferLength: 15,
            maxMaxBufferLength: 20,
            backbufferLength: 0,
            startFragPrefetch: true,
            startLevel: 0,
            abrEwmaDefaultEstimate: 100000,
            abrEwmaFastVoD: 3.0,
            abrEwmaSlowVoD: 5.0,
            abrBandWidthFactor: 0.8,
            abrBandWidthUpFactor: 0.7,
            capLevelToPlayerSize: true,
            capLevelOnFPSDrop: true,
            renderNudge: true,
            maxStarvationDelay: 30,
            starvationDelay: 20,
            nudgeOffset: 0.5,
            enableSoftNudge: false,
            fragLoadingTimeOut: 20000,
            liveDurationInfinity: true,
        };
    }

    attachMedia() {
        if (this.destroyed || !this.video) return;

        if (Hls.isSupported()) {
            this.hls = new Hls(this.getConfig());
            this.hls.attachMedia(this.video);
            this.hls.loadSource(this.streamUrl);
            this.bindHlsEvents();
        } else if (this.video.canPlayType("application/vnd.apple.mpegurl")) {
            this.video.src = this.streamUrl;
            this.bindNativeEvents();
        } else {
            this.showMessage("HLS not supported");
        }
    }

    bindHlsEvents() {
        this.hls.on(Hls.Events.MANIFEST_PARSED, () => {
            this.hidePlaceholder();
            this.video.play().catch(() => {});
            this.startTime = Date.now();
            this._lastFragTime = Date.now();
            this.startStaleCheck();
            this.track("play");
        });

        this.hls.on(Hls.Events.FRAG_LOADED, () => {
            this._lastFragTime = Date.now();
        });

        this.hls.on(Hls.Events.LEVEL_SWITCHED, (_, data) => {
            const level = this.hls.levels?.[data.level];
            if (level) {
                this.currentBitrate = level.bitrate;
                this.currentLevel = data.level;
                this.track("level_switch", {
                    bitrate_kbps: Math.round(level.bitrate / 1000),
                    resolution: level.width
                        ? `${level.width}x${level.height}`
                        : null,
                });
            }
        });

        this.hls.on(Hls.Events.ERROR, (_, data) => {
            if (data.fatal) {
                if (data.response?.code === 404) {
                    this.track("error", {
                        error_message:
                            "manifest 404 — marking offline immediately",
                    });
                    this.markOffline();
                    return;
                }
                if (
                    data.type === Hls.ErrorTypes.NETWORK_ERROR &&
                    data.details === "bufferStalledError"
                ) {
                    const next = Math.max(0, (this.currentLevel || 0) - 1);
                    this.hls.currentLevel = next;
                    this.track("error", {
                        error_message: `bufferStalledError — switching to level ${next}`,
                    });
                    return;
                }
                this.track("error", {
                    error_message: `${data.type}:${data.details}`,
                });
                this.attemptReconnect();
            } else {
                if (
                    data.details === "levelLoadError" &&
                    data.response?.code === 404
                ) {
                    this._subPlaylistRetries++;
                    if (this._subPlaylistRetries >= 3) {
                        this.track("error", {
                            error_message: "sub-playlist 404 — marking offline",
                        });
                        this.markOffline();
                        return;
                    }
                    this.track("error", {
                        error_message: `sub-playlist 404 — reloading (${this._subPlaylistRetries}/3)`,
                    });
                    setTimeout(() => {
                        if (!this.destroyed && this.hls) {
                            const url = this.hls.url;
                            this.destroyHls();
                            this.hls = new Hls(this.getConfig());
                            this.hls.loadSource(url);
                            this.hls.attachMedia(this.video);
                            this.bindHlsEvents();
                        }
                    }, 2000 * this._subPlaylistRetries);
                    return;
                }
                this.track("error", {
                    error_message: `${data.type}:${data.details}`,
                });
            }
        });

        this.video.addEventListener("waiting", () => {
            if (this.bufferingStart > 0) return;
            this.bufferingStart = Date.now();
            this.track("buffering_start");
        });

        this.video.addEventListener("playing", () => {
            if (this.bufferingStart > 0) {
                const duration = Date.now() - this.bufferingStart;
                this.track("buffering_end", {
                    latency_ms: duration,
                });
                this.bufferingStart = 0;
            }
        });

        this.video.addEventListener("stalled", () => {
            this.track("error", {
                error_message: "video stalled",
            });
        });
    }

    bindNativeEvents() {
        this.video.addEventListener("loadedmetadata", () => {
            this.hidePlaceholder();
            this.video.play().catch(() => {});
            this.startTime = Date.now();
            this.track("play");
        });

        this.video.addEventListener("error", () => {
            this.showMessage("Stream unavailable");
            this.track("error", {
                error_message: "native playback error",
            });
        });

        this.video.addEventListener("waiting", () => {
            this.bufferingStart = Date.now();
            this.track("buffering_start");
        });

        this.video.addEventListener("playing", () => {
            if (this.bufferingStart > 0) {
                this.track("buffering_end", {
                    latency_ms: Date.now() - this.bufferingStart,
                });
                this.bufferingStart = 0;
            }
        });
    }

    startStaleCheck() {
        this.stopStaleCheck();
        this._staleTimer = setInterval(() => {
            if (this.destroyed) return;
            if (this._lastFragTime === 0) return;
            const elapsed = Date.now() - this._lastFragTime;
            if (elapsed > STALE_TIMEOUT) {
                this.track("error", {
                    error_message: "stream ended - no fragments",
                });
                this.markOffline();
            }
        }, 15000);
    }

    stopStaleCheck() {
        if (this._staleTimer) {
            clearInterval(this._staleTimer);
            this._staleTimer = null;
        }
    }

    markOffline() {
        this.destroyHls();
        this.stopStaleCheck();
        if (this._onOffline) {
            this._onOffline(this.cameraId);
        }
    }

    attemptReconnect() {
        if (this.destroyed) return;
        if (this.reconnectAttempts >= RECONNECT_MAX) {
            this.showMessage("Stream unavailable");
            this.markOffline();
            return;
        }

        this.stopStaleCheck();
        this.destroyHls();

        const delay = RECONNECT_DELAYS[this.reconnectAttempts];
        this.reconnectAttempts++;

        this.track("reconnect", {
            error_message: `attempt ${this.reconnectAttempts}/${RECONNECT_MAX}`,
        });

        this.showMessage(
            `Reconnecting... (${this.reconnectAttempts}/${RECONNECT_MAX})`,
        );

        this.reconnectTimer = setTimeout(() => {
            if (!this.destroyed) {
                this.attachMedia();
            }
        }, delay);
    }

    destroyHls() {
        if (this.hls) {
            try {
                this.hls.destroy();
            } catch (e) {
                /* ignore */
            }
            this.hls = null;
        }
    }

    suspend() {
        if (this.destroyed || this._suspended) return;
        this._suspended = true;
        if (this.hls) {
            this.hls.stopLoad();
        }
        if (this.video && !this.video.paused) {
            this.video.pause();
        }
    }

    resume() {
        if (this.destroyed || !this._suspended) return;
        this._suspended = false;
        if (this.hls) {
            this.hls.startLoad();
        }
        if (this.video) {
            this.video.play().catch(() => {});
        }
    }

    destroy() {
        this.destroyed = true;
        clearTimeout(this.reconnectTimer);
        this.stopStaleCheck();
        this.destroyHls();
    }

    hidePlaceholder() {
        if (this.placeholder) {
            this.placeholder.style.display = "none";
        }
    }

    showMessage(text) {
        if (this.placeholder) {
            const textEl = this.placeholder.querySelector(".placeholder-text");
            if (textEl) {
                textEl.textContent = text;
            } else {
                this.placeholder.textContent = text;
            }
        }
    }

    getCurrentBitrate() {
        return this.currentBitrate;
    }

    getBufferHealth() {
        if (!this.video) return 0;
        const buffered = this.video.buffered;
        if (buffered.length === 0) return 0;
        return buffered.end(buffered.length - 1) - this.video.currentTime;
    }

    track(eventType, extra = {}) {
        if (!this.telemetry) return;
        this.telemetry.track({
            camera_id: this.cameraId,
            event_type: eventType,
            bitrate_kbps: this.currentBitrate
                ? Math.round(this.currentBitrate / 1000)
                : null,
            buffer_health: this.getBufferHealth(),
            ...extra,
        });
    }
}
