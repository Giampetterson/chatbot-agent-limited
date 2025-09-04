// Modulo registrazione vocale e trascrizione
class VoiceRecorder {
    constructor() {
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;
        this.stream = null;
        this.recordButton = null;
        this.visualizer = null;
        this.animationId = null;
        this.audioContext = null;
        this.analyser = null;
        this.dataArray = null;
    }

    // Inizializza il registratore
    async init() {
        try {
            // Richiedi permesso microfono
            this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            
            // Setup audio context per visualizzazione
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const source = this.audioContext.createMediaStreamSource(this.stream);
            this.analyser = this.audioContext.createAnalyser();
            this.analyser.fftSize = 256;
            const bufferLength = this.analyser.frequencyBinCount;
            this.dataArray = new Uint8Array(bufferLength);
            source.connect(this.analyser);
            
            // Crea MediaRecorder
            const mimeType = this.getSupportedMimeType();
            this.mediaRecorder = new MediaRecorder(this.stream, { mimeType });
            
            // Gestione eventi
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                }
            };
            
            this.mediaRecorder.onstop = async () => {
                const audioBlob = new Blob(this.audioChunks, { type: this.mediaRecorder.mimeType });
                this.audioChunks = [];
                await this.sendAudioForTranscription(audioBlob);
            };
            
            return true;
        } catch (error) {
            console.error('Errore inizializzazione microfono:', error);
            this.showError('Impossibile accedere al microfono. Verifica i permessi del browser.');
            return false;
        }
    }

    // Determina il MIME type supportato
    getSupportedMimeType() {
        const types = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/ogg;codecs=opus',
            'audio/mp4',
            'audio/mpeg'
        ];
        
        for (const type of types) {
            if (MediaRecorder.isTypeSupported(type)) {
                console.log('Usando MIME type:', type);
                return type;
            }
        }
        
        return 'audio/webm'; // Fallback
    }

    // Avvia registrazione
    async startRecording() {
        if (!this.mediaRecorder) {
            const initialized = await this.init();
            if (!initialized) return;
        }
        
        if (this.mediaRecorder.state === 'inactive') {
            this.audioChunks = [];
            this.mediaRecorder.start();
            this.isRecording = true;
            this.updateUI(true);
            this.startVisualization();
            console.log('Registrazione avviata');
        }
    }

    // Ferma registrazione
    stopRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
            this.mediaRecorder.stop();
            this.isRecording = false;
            this.updateUI(false);
            this.stopVisualization();
            console.log('Registrazione fermata');
        }
    }

    // Toggle registrazione
    toggleRecording() {
        if (this.isRecording) {
            this.stopRecording();
        } else {
            this.startRecording();
        }
    }

    // Invia audio per trascrizione
    async sendAudioForTranscription(audioBlob) {
        try {
            // Mostra indicatore caricamento
            this.showLoading(true);
            
            console.log('Invio audio per trascrizione...');
            console.log('Blob type:', audioBlob.type);
            console.log('Blob size:', audioBlob.size);
            
            // Converti in formato compatibile se necessario
            const audioFile = new File([audioBlob], 'recording.webm', { 
                type: audioBlob.type || 'audio/webm' 
            });
            
            // Prepara FormData
            const formData = new FormData();
            formData.append('audio', audioFile);
            
            // Invia richiesta
            console.log('Invio richiesta a /voice-transcription.php');
            const response = await fetch('/voice-transcription.php', {
                method: 'POST',
                body: formData
            });
            
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Response error text:', errorText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log('Response JSON:', result);
            
            if (result.success && result.text) {
                // Inserisci testo trascritto nell'input
                this.insertTranscribedText(result.text);
                console.log('Trascrizione completata:', result.text);
            } else {
                throw new Error(result.error || 'Errore trascrizione');
            }
            
        } catch (error) {
            console.error('Errore dettagliato trascrizione:', error);
            console.error('Stack trace:', error.stack);
            this.showError('Errore durante la trascrizione. Riprova.');
        } finally {
            this.showLoading(false);
        }
    }

    // Inserisci testo trascritto nell'input
    insertTranscribedText(text) {
        // Cerca prima chat-input (chatbot.html) poi user-input (test-voice.html)
        const input = document.getElementById('chat-input') || document.getElementById('user-input');
        if (input) {
            // Aggiungi al testo esistente se presente
            if (input.value.trim()) {
                input.value += ' ' + text;
            } else {
                input.value = text;
            }
            
            console.log('Testo inserito nel campo input:', text);
            
            // Trigger evento input per eventuali listener (importante per il toggle!)
            input.dispatchEvent(new Event('input', { bubbles: true }));
            
            // Focus sull'input
            input.focus();
            
            console.log('Evento input dispatched');
        }
    }

    // Aggiorna UI durante registrazione
    updateUI(recording) {
        if (this.recordButton) {
            const micIcon = document.getElementById('mic-icon');
            const sendIcon = document.getElementById('send-icon');
            const voiceWaves = document.getElementById('voice-waves');
            
            if (recording) {
                this.recordButton.classList.add('recording');
                this.recordButton.title = 'Ferma registrazione';
                
                // Nascondi icona microfono e mostra onde
                if (micIcon) micIcon.style.display = 'none';
                if (sendIcon) sendIcon.style.display = 'none';
                if (voiceWaves) voiceWaves.classList.add('active');
                
            } else {
                this.recordButton.classList.remove('recording');
                this.recordButton.title = 'Registra messaggio vocale';
                
                // Mostra microfono e nascondi onde
                if (micIcon) micIcon.style.display = 'block';
                if (sendIcon) sendIcon.style.display = 'none';
                if (voiceWaves) voiceWaves.classList.remove('active');
            }
        }
    }

    // Visualizzazione audio durante registrazione
    startVisualization() {
        if (!this.analyser) return;
        
        const voiceWaves = document.getElementById('voice-waves');
        if (!voiceWaves) return;
        
        const waves = voiceWaves.querySelectorAll('.voice-wave');
        
        const draw = () => {
            this.animationId = requestAnimationFrame(draw);
            this.analyser.getByteFrequencyData(this.dataArray);
            
            // Calcola livello medio
            let sum = 0;
            for (let i = 0; i < this.dataArray.length; i++) {
                sum += this.dataArray[i];
            }
            const average = sum / this.dataArray.length;
            const intensity = Math.max(0.2, average / 255); // Minimo 20% per animazione sempre visibile
            
            // Aggiorna altezza delle onde in base al livello audio
            waves.forEach((wave, index) => {
                const baseHeight = [8, 12, 16, 12, 8][index];
                const dynamicHeight = baseHeight * (0.5 + intensity);
                wave.style.height = `${dynamicHeight}px`;
            });
            
            // Scala leggera del pulsante
            const scale = 1 + (intensity * 0.1);
            if (this.recordButton) {
                this.recordButton.style.transform = `scale(${scale})`;
            }
        };
        
        draw();
    }

    // Ferma visualizzazione
    stopVisualization() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
        
        if (this.recordButton) {
            this.recordButton.style.transform = 'scale(1)';
        }
        
        // Reset onde sonore
        const voiceWaves = document.getElementById('voice-waves');
        if (voiceWaves) {
            const waves = voiceWaves.querySelectorAll('.voice-wave');
            waves.forEach((wave, index) => {
                const baseHeight = [8, 12, 16, 12, 8][index];
                wave.style.height = `${baseHeight}px`;
            });
        }
    }

    // Mostra indicatore caricamento
    showLoading(show) {
        const input = document.getElementById('chat-input') || document.getElementById('user-input');
        if (input) {
            const originalPlaceholder = input.placeholder || 'Scrivi un messaggio...';
            if (show) {
                input.placeholder = 'Trascrizione in corso...';
                input.disabled = true;
            } else {
                input.placeholder = originalPlaceholder;
                input.disabled = false;
            }
        }
    }

    // Mostra errore
    showError(message) {
        // Crea toast notification
        const toast = document.createElement('div');
        toast.className = 'voice-error-toast';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: #e74c3c;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 10000;
            animation: slideUp 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideDown 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Cleanup
    destroy() {
        this.stopRecording();
        
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        
        if (this.audioContext) {
            this.audioContext.close();
            this.audioContext = null;
        }
        
        this.mediaRecorder = null;
        this.audioChunks = [];
    }
}

// Stili animazioni
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }
    
    @keyframes slideDown {
        from {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        to {
            opacity: 0;
            transform: translateX(-50%) translateY(20px);
        }
    }
    
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        }
    }
    
    .voice-record-btn {
        background: #0C2267;
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .voice-record-btn:hover {
        background: #1F7138;
        transform: scale(1.1);
    }
    
    .voice-record-btn.recording {
        background: #dc3545;
        animation: pulse 1.5s infinite;
    }
    
    .voice-record-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
`;
document.head.appendChild(style);

// Esporta istanza globale
window.voiceRecorder = new VoiceRecorder();