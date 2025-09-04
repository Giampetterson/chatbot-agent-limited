/**
 * User Fingerprinting System v1.0
 * Sistema di identificazione utenti per rate limiting
 */

class UserFingerprint {
    constructor() {
        this.fingerprintData = null;
        this.userId = null;
        this.storageKey = 'rentria_user_id';
    }

    /**
     * Genera fingerprint completo del browser/dispositivo
     */
    async generateFingerprint() {
        const components = {
            // Screen information
            screen: `${screen.width}x${screen.height}x${screen.colorDepth}`,
            screenAvail: `${screen.availWidth}x${screen.availHeight}`,
            
            // Browser information
            userAgent: navigator.userAgent,
            language: navigator.language,
            languages: navigator.languages ? navigator.languages.join(',') : '',
            platform: navigator.platform,
            cookieEnabled: navigator.cookieEnabled,
            
            // Timezone
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            timezoneOffset: new Date().getTimezoneOffset(),
            
            // Canvas fingerprinting
            canvas: await this.getCanvasFingerprint(),
            
            // WebGL fingerprinting
            webgl: this.getWebGLFingerprint(),
            
            // Audio context fingerprinting
            audioContext: await this.getAudioFingerprint(),
            
            // Additional entropy
            deviceMemory: navigator.deviceMemory || 'unknown',
            hardwareConcurrency: navigator.hardwareConcurrency || 'unknown',
            maxTouchPoints: navigator.maxTouchPoints || 0,
            
            // Session info
            timestamp: Date.now(),
            sessionId: this.generateSessionId()
        };

        this.fingerprintData = components;
        return components;
    }

    /**
     * Canvas fingerprinting per maggiore unicità
     */
    async getCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            // Disegna pattern complesso per fingerprinting
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('RentrIA Fingerprint 🤖', 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('User ID System', 4, 45);
            
            // Aggiungi forme geometriche
            ctx.globalCompositeOperation = 'multiply';
            ctx.fillStyle = 'rgb(255,0,255)';
            ctx.beginPath();
            ctx.arc(50, 50, 50, 0, Math.PI * 2, true);
            ctx.closePath();
            ctx.fill();
            
            return canvas.toDataURL();
        } catch (e) {
            return 'canvas_error';
        }
    }

    /**
     * WebGL fingerprinting
     */
    getWebGLFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            
            if (!gl) return 'no_webgl';
            
            const info = {
                vendor: gl.getParameter(gl.VENDOR),
                renderer: gl.getParameter(gl.RENDERER),
                version: gl.getParameter(gl.VERSION),
                shadingLanguageVersion: gl.getParameter(gl.SHADING_LANGUAGE_VERSION),
                extensions: gl.getSupportedExtensions()?.join(',') || ''
            };
            
            return JSON.stringify(info);
        } catch (e) {
            return 'webgl_error';
        }
    }

    /**
     * Audio context fingerprinting
     */
    async getAudioFingerprint() {
        return new Promise((resolve) => {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) {
                    resolve('no_audio_context');
                    return;
                }

                const context = new AudioContext();
                const oscillator = context.createOscillator();
                const analyser = context.createAnalyser();
                const gainNode = context.createGain();
                const scriptProcessor = context.createScriptProcessor(4096, 1, 1);

                oscillator.type = 'triangle';
                oscillator.frequency.setValueAtTime(10000, context.currentTime);
                
                gainNode.gain.setValueAtTime(0, context.currentTime);
                
                oscillator.connect(analyser);
                analyser.connect(scriptProcessor);
                scriptProcessor.connect(gainNode);
                gainNode.connect(context.destination);

                oscillator.start(0);
                
                scriptProcessor.onaudioprocess = function(bins) {
                    const data = new Float32Array(analyser.frequencyBinCount);
                    analyser.getFloatFrequencyData(data);
                    
                    // Calcola hash dei dati audio
                    const sum = data.reduce((a, b) => a + b, 0);
                    const audioHash = btoa(sum.toString()).substring(0, 20);
                    
                    oscillator.disconnect();
                    scriptProcessor.disconnect();
                    context.close();
                    
                    resolve(audioHash);
                };

                // Timeout fallback
                setTimeout(() => {
                    resolve('audio_timeout');
                }, 1000);

            } catch (e) {
                resolve('audio_error');
            }
        });
    }

    /**
     * Genera session ID unico per questa sessione
     */
    generateSessionId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Crea hash SHA-256 del fingerprint
     */
    async createHash(data) {
        const jsonString = JSON.stringify(data, Object.keys(data).sort());
        const encoder = new TextEncoder();
        const dataBuffer = encoder.encode(jsonString);
        const hashBuffer = await crypto.subtle.digest('SHA-256', dataBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Ottiene o genera l'ID utente
     */
    async getUserId() {
        try {
            // Prima controlla localStorage
            let storedId = localStorage.getItem(this.storageKey);
            
            if (storedId) {
                try {
                    const parsed = JSON.parse(storedId);
                    if (parsed.id && parsed.timestamp && (Date.now() - parsed.timestamp < 86400000)) {
                        // ID valido (meno di 24h)
                        this.userId = parsed.id;
                        console.log('🔍 Using stored user ID:', parsed.id.substring(0, 12) + '...');
                        return parsed.id;
                    }
                } catch (e) {
                    console.warn('Invalid stored user ID, regenerating...');
                }
            }

            // Genera nuovo fingerprint
            console.log('🔍 Generating new user fingerprint...');
            const fingerprint = await this.generateFingerprint();
            const hash = await this.createHash(fingerprint);
            
            // Crea ID combinato con timestamp per unicità
            const userId = `fp_${hash}_${Date.now().toString(36)}`;
            
            // Salva in localStorage
            const storageData = {
                id: userId,
                timestamp: Date.now(),
                fingerprint: {
                    screen: fingerprint.screen,
                    userAgent: fingerprint.userAgent.substring(0, 50) + '...',
                    timezone: fingerprint.timezone
                }
            };
            
            localStorage.setItem(this.storageKey, JSON.stringify(storageData));
            
            this.userId = userId;
            console.log('🔍 Generated new user ID:', userId.substring(0, 12) + '...');
            console.log('🔍 Fingerprint components:', Object.keys(fingerprint).length);
            
            return userId;
        } catch (error) {
            console.error('Error generating fingerprint, using fallback:', error);
            
            // Fallback per mobile o browser con restrizioni
            const fallbackId = this.generateFallbackId();
            
            // Salva fallback in localStorage
            const storageData = {
                id: fallbackId,
                timestamp: Date.now(),
                isFallback: true
            };
            
            localStorage.setItem(this.storageKey, JSON.stringify(storageData));
            this.userId = fallbackId;
            
            console.log('🔍 Using fallback user ID:', fallbackId.substring(0, 12) + '...');
            return fallbackId;
        }
    }
    
    /**
     * Genera un ID di fallback per browser mobile o con restrizioni
     */
    generateFallbackId() {
        // Usa informazioni base disponibili
        const basicInfo = [
            navigator.userAgent || 'unknown',
            screen.width || 0,
            screen.height || 0,
            navigator.language || 'en',
            new Date().getTimezoneOffset(),
            Math.random().toString(36)
        ].join('_');
        
        // Crea un hash semplice
        let hash = 0;
        for (let i = 0; i < basicInfo.length; i++) {
            const char = basicInfo.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        
        // Converti in esadecimale e padda a 64 caratteri
        const hexHash = Math.abs(hash).toString(16).padEnd(64, '0');
        
        // Formato compatibile con il validatore
        return `fp_${hexHash}_${Date.now().toString(36)}`;
    }

    /**
     * Valida formato user ID
     */
    isValidUserId(userId) {
        if (!userId || typeof userId !== 'string') return false;
        return /^fp_[a-f0-9]{64}_[a-z0-9]+$/.test(userId);
    }

    /**
     * Pulisce dati obsoleti
     */
    cleanup() {
        try {
            const stored = localStorage.getItem(this.storageKey);
            if (stored) {
                const parsed = JSON.parse(stored);
                // Rimuovi se più vecchio di 24h
                if (Date.now() - parsed.timestamp > 86400000) {
                    localStorage.removeItem(this.storageKey);
                    console.log('🧹 Cleaned up old user ID');
                }
            }
        } catch (e) {
            localStorage.removeItem(this.storageKey);
        }
    }

    /**
     * Debug info per testing
     */
    async getDebugInfo() {
        const fingerprint = this.fingerprintData || await this.generateFingerprint();
        return {
            userId: this.userId,
            components: Object.keys(fingerprint).length,
            screen: fingerprint.screen,
            timezone: fingerprint.timezone,
            userAgent: fingerprint.userAgent.substring(0, 100) + '...',
            canvasSupport: fingerprint.canvas !== 'canvas_error',
            webglSupport: fingerprint.webgl !== 'no_webgl',
            audioSupport: fingerprint.audioContext !== 'no_audio_context'
        };
    }
}

// Export per utilizzo globale
window.UserFingerprint = UserFingerprint;