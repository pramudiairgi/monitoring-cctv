import Hls from 'hls.js';

const RECONNECT_DELAYS = [2000, 4000, 8000];
const RECONNECT_MAX = 3;

export default class StreamManager {
  constructor(cameraId, videoElement, streamUrl, adaptiveUrl, telemetry) {
    this.cameraId = cameraId;
    this.video = videoElement;
    this.streamUrl = streamUrl;
    this.adaptiveUrl = adaptiveUrl || null;
    this.telemetry = telemetry || null;
    this.hls = null;
    this.reconnectAttempts = 0;
    this.reconnectTimer = null;
    this.destroyed = false;
    this.startTime = 0;
    this.bufferingStart = 0;
    this.currentBitrate = 0;
    this.currentLevel = -1;
    this.placeholder = this.video?.closest('.camera-cell')
      ?.querySelector('.camera-placeholder');
  }

  getConfig() {
    return {
      enableWorker: true,
      lowLatencyMode: true,
      liveSyncDuration: 3,
      liveMaxLatencyDuration: 6,
      maxBufferLength: 15,
      maxMaxBufferLength: 30,
      startLevel: -1,
      abrEwmaDefaultEstimate: 500000,
      abrBandwidthUpFactor: 0.7,
      abrBandwidthDownFactor: 0.4,
      capLevelToPlayerSize: true,
    };
  }

  attachMedia() {
    if (this.destroyed || !this.video) return;

    const url = this.adaptiveUrl || this.streamUrl;

    if (Hls.isSupported()) {
      this.hls = new Hls(this.getConfig());
      this.hls.loadSource(url);
      this.hls.attachMedia(this.video);
      this.bindHlsEvents();
    } else if (this.video.canPlayType('application/vnd.apple.mpegurl')) {
      this.video.src = url;
      this.bindNativeEvents();
    } else {
      this.showMessage('HLS not supported');
    }
  }

  bindHlsEvents() {
    this.hls.on(Hls.Events.MANIFEST_PARSED, () => {
      this.hidePlaceholder();
      this.video.play().catch(() => {});
      this.startTime = Date.now();
      this.track('play');
    });

    this.hls.on(Hls.Events.LEVEL_SWITCHED, (_, data) => {
      const level = this.hls.levels?.[data.level];
      if (level) {
        this.currentBitrate = level.bitrate;
        this.currentLevel = data.level;
        this.track('level_switch', {
          bitrate_kbps: Math.round(level.bitrate / 1000),
          resolution: level.width ? `${level.width}x${level.height}` : null,
        });
      }
    });

    this.hls.on(Hls.Events.ERROR, (_, data) => {
      if (data.fatal) {
        this.track('error', {
          error_message: `${data.type}:${data.details}`,
        });
        this.attemptReconnect();
      } else {
        this.track('error', {
          error_message: `${data.type}:${data.details}`,
        });
      }
    });

    this.video.addEventListener('waiting', () => {
      this.bufferingStart = Date.now();
      this.track('buffering_start');
    });

    this.video.addEventListener('playing', () => {
      if (this.bufferingStart > 0) {
        const duration = Date.now() - this.bufferingStart;
        this.track('buffering_end', {
          latency_ms: duration,
        });
        this.bufferingStart = 0;
      }
    });

    this.video.addEventListener('stalled', () => {
      this.track('error', {
        error_message: 'video stalled',
      });
    });
  }

  bindNativeEvents() {
    this.video.addEventListener('loadedmetadata', () => {
      this.hidePlaceholder();
      this.video.play().catch(() => {});
      this.startTime = Date.now();
      this.track('play');
    });

    this.video.addEventListener('error', () => {
      this.showMessage('Stream unavailable');
      this.track('error', {
        error_message: 'native playback error',
      });
    });

    this.video.addEventListener('waiting', () => {
      this.bufferingStart = Date.now();
      this.track('buffering_start');
    });

    this.video.addEventListener('playing', () => {
      if (this.bufferingStart > 0) {
        this.track('buffering_end', {
          latency_ms: Date.now() - this.bufferingStart,
        });
        this.bufferingStart = 0;
      }
    });
  }

  attemptReconnect() {
    if (this.destroyed) return;
    if (this.reconnectAttempts >= RECONNECT_MAX) {
      this.showMessage('Stream unavailable');
      return;
    }

    this.destroyHls();

    const delay = RECONNECT_DELAYS[this.reconnectAttempts];
    this.reconnectAttempts++;

    this.track('reconnect', {
      error_message: `attempt ${this.reconnectAttempts}/${RECONNECT_MAX}`,
    });

    this.showMessage(`Reconnecting... (${this.reconnectAttempts}/${RECONNECT_MAX})`);

    this.reconnectTimer = setTimeout(() => {
      if (!this.destroyed) {
        this.attachMedia();
      }
    }, delay);
  }

  destroyHls() {
    if (this.hls) {
      try { this.hls.destroy(); } catch (e) { /* ignore */ }
      this.hls = null;
    }
  }

  destroy() {
    this.destroyed = true;
    clearTimeout(this.reconnectTimer);
    this.destroyHls();
  }

  hidePlaceholder() {
    if (this.placeholder) {
      this.placeholder.style.display = 'none';
    }
  }

  showMessage(text) {
    if (this.placeholder) {
      this.placeholder.textContent = text;
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
      bitrate_kbps: this.currentBitrate ? Math.round(this.currentBitrate / 1000) : null,
      buffer_health: this.getBufferHealth(),
      ...extra,
    });
  }
}
