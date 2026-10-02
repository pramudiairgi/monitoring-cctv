import Hls from "hls.js";

const RECONNECT_DELAYS = [1000, 2000, 4000, 8000, 15000];
const RECONNECT_MAX = 999;
const STALE_TIMEOUT = 60000;

export default class StreamManager {
    constructor(cameraId, videoElement, streamUrl, telemetry, onOffline, isAdaptive = true) {
        this.cameraId = cameraId;
        this.video = videoElement;
        this.streamUrl = streamUrl;
        this.telemetry = telemetry || null;
        this._onOffline = onOffline || null;
        this.isAdaptive = isAdaptive;
        this.hls = null;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
        this.hasAudio = false;
        this._audioProbe = null;
        this.userPaused = false;
        this.destroyed = false;
        this.startTime = 0;
        this.bufferingStart = 0;
        this.currentBitrate = 0;
        this.currentLevel = -1;
        this._lastFragTime = 0;
        this._lastSegmentSize = 0;
        this._lastDownloadTime = 0;
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

    getConfig(isAdaptive = this.isAdaptive) {
        if (isAdaptive) {
            return {
                enableWorker: true,
                lowLatencyMode: true,
                useFetch: true,
                liveSyncDuration: 4,
                // Tolerate being behind live (rewind/DVR); eviction by the
                // server window governs where smaller. Requires a matching
                // server playlist window (target ~4-5 min for 2-3 min behind).
                // Joining still starts near-live.
                liveMaxLatencyDuration: 200,
                maxBufferLength: 8,
                maxMaxBufferLength: 12,
                backbufferLength: 3,
                startFragPrefetch: true,
                startLevel: 0,
                abrEwmaDefaultEstimate: 500000,
                abrEwmaFastVoD: 3.0,
                abrEwmaSlowVoD: 5.0,
                abrBandWidthFactor: 0.5,
                abrBandWidthUpFactor: 0.5,
                capLevelToPlayerSize: true,
                capLevelOnFPSDrop: true,
                renderNudge: true,
                maxStarvationDelay: 4,
                starvationDelay: 2,
                nudgeOffset: 0.5,
                enableSoftNudge: false,
                fragLoadingTimeOut: 6000,
                liveDurationInfinity: true,
            };
        }
        return {
            enableWorker: true,
            lowLatencyMode: false,
            useFetch: true,
            liveSyncDuration: 8,
            liveMaxLatencyDuration: 16,
            maxBufferLength: 18,
            maxMaxBufferLength: 30,
            backbufferLength: 8,
            startFragPrefetch: true,
            startLevel: 0,
            abrEwmaDefaultEstimate: 500000,
            abrEwmaFastVoD: 3.0,
            abrEwmaSlowVoD: 5.0,
            abrBandWidthFactor: 0.5,
            abrBandWidthUpFactor: 0.5,
            capLevelToPlayerSize: true,
            capLevelOnFPSDrop: true,
            renderNudge: true,
            maxStarvationDelay: 4,
            starvationDelay: 2,
            nudgeOffset: 0.5,
            enableSoftNudge: false,
            fragLoadingTimeOut: 12000,
            liveDurationInfinity: true,
        };
    }

    attachMedia() {
        if (this.destroyed || !this.video) return;

        this.showPlaceholder();
        if (this.video) this.video.style.display = "";

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
            this.updateAudioState();
            this.video.play().catch(() => {});
            this.startTime = Date.now();
            this._lastFragTime = Date.now();
            this.startStaleCheck();
            this.track("play");
        });

        this.video.addEventListener("loadeddata", () => {
            this.hidePlaceholder();
        }, { once: true });

        this.hls.on(Hls.Events.FRAG_LOADED, (_, data) => {
            this._lastFragTime = Date.now();
            const stats = data.frag?.stats;
            if (stats) {
                this._lastSegmentSize = stats.loaded;
                this._lastDownloadTime = (stats.loading?.end ?? 0) - (stats.loading?.start ?? Date.now());
            }
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
            this.notifyAudioState();
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
            this.video.play().catch(() => {});
            this.startTime = Date.now();
            this.updateAudioState();
            this.track("play");
        });

        this.video.addEventListener("loadeddata", () => {
            this.hidePlaceholder();
        }, { once: true });

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
            this.notifyAudioState();
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

    updateAudioState() {
        const check = () => {
            if (this.destroyed) return false;
            const hlsCount = this.hls?.audioTracks?.length || 0;
            const elCount = this.video?.audioTracks?.length || 0;
            const decoded = this.video?.webkitAudioDecodedByteCount || 0;
            if (
                hlsCount > 0 ||
                elCount > 0 ||
                decoded > 0 ||
                this.video?.mozHasAudio === true
            ) {
                this.setHasAudio(true);
                return true;
            }
            return false;
        };
        if (check()) return;
        clearTimeout(this._audioProbe);
        this._audioProbe = setTimeout(() => {
            if (!this.destroyed) check();
        }, 5000);
    }

    setHasAudio(v) {
        if (!v || this.destroyed) return;
        this.hasAudio = true;
        this.notifyAudioState();
    }

    notifyAudioState() {
        if (this.destroyed || !this.video) return;
        this.video.dispatchEvent(
            new CustomEvent("stream-audio", {
                bubbles: true,
                detail: { cameraId: this.cameraId },
            }),
        );
    }

    setMuted(muted) {
        if (this.video) this.video.muted = muted;
    }

    pauseUser() {
        if (this.destroyed) return;
        this.userPaused = true;
        this.stopStaleCheck();
        if (this.hls) {
            try {
                this.hls.stopLoad();
            } catch {
                /* ignore */
            }
        }
        if (this.video && !this.video.paused) this.video.pause();
        this.track("pause");
    }

    resumeUser() {
        if (this.destroyed) return;
        if (this._suspended) this.resume();
        this.userPaused = false;
        this._lastFragTime = 0;
        this.startStaleCheck();
        this.syncToLiveEdge();
        if (this.hls) {
            try {
                this.hls.startLoad();
            } catch {
                /* ignore */
            }
        }
        if (this.video) {
            const played = this.video.play();
            if (played && typeof played.catch === "function") played.catch(() => {});
        }
        this.track("resume");
    }

    getDvrWindow() {
        const v = this.video;
        if (!v) return null;
        try {
            const s = v.seekable;
            if (!s || s.length === 0) return null;
            return { start: s.start(0), end: s.end(s.length - 1) };
        } catch {
            return null;
        }
    }

    liveEdge() {
        if (this.hls && Number.isFinite(this.hls.liveSyncPosition)) {
            return this.hls.liveSyncPosition;
        }
        const w = this.getDvrWindow();
        return w ? w.end : null;
    }

    secondsBehindLive() {
        const e = this.liveEdge();
        if (e === null || e === undefined || !this.video) return 0;
        return Math.max(0, e - this.video.currentTime);
    }

    syncToLiveEdge(margin = 3) {
        const e = this.liveEdge();
        if (e === null || e === undefined || !this.video) return;
        try {
            const target = Math.max(0, e - margin);
            if (Math.abs(this.video.currentTime - target) > 1.5) {
                this.video.currentTime = target;
            }
        } catch {
            /* unseekable */
        }
    }

    seekTo(t) {
        const v = this.video;
        if (!v || this.destroyed) return;
        const w = this.getDvrWindow();
        let target = t;
        // Keep a margin from the sliding-window start so the position is
        // not evicted out from under us the moment the playlist advances.
        if (w) target = Math.min(Math.max(t, w.start + 1), w.end);
        try {
            v.currentTime = Math.max(0, target);
        } catch {
            /* unseekable */
        }
        this.track("seek", { target_sec: Math.round(target) });
    }

    seekBy(sec) {
        if (!this.video || this.destroyed) return;
        this.seekTo(this.video.currentTime + sec);
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
        clearTimeout(this._audioProbe);
        this.stopStaleCheck();
        this.destroyHls();
    }

    hidePlaceholder() {
        if (this.placeholder) {
            this.placeholder.classList.add("placeholder-hidden");
        }
    }

    showPlaceholder() {
        if (this.placeholder) {
            this.placeholder.classList.remove("placeholder-hidden");
            this.placeholder.style.display = "";
            this.placeholder.style.opacity = "";
        }
    }

    showMessage(text) {
        this.showPlaceholder();
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
