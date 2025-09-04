/**
 * RentrIA Chat Widget - JavaScript
 * Version: 1.0.0
 */

class RentriaChatManager {
    constructor(widgetId, config) {
        this.widgetId = widgetId;
        this.config = config;
        this.container = document.getElementById(widgetId);
        this.messageList = document.getElementById(`${widgetId}-messages`);
        this.input = document.getElementById(`${widgetId}-input`);
        this.sendBtn = document.getElementById(`${widgetId}-send`);
        this.pdfBtn = document.getElementById(`${widgetId}-pdf`);
        
        this.messages = [];
        this.isProcessing = false;
        
        this.init();
    }
    
    init() {
        // Event listeners
        this.sendBtn.addEventListener('click', () => this.sendMessage());
        this.input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        if (this.pdfBtn) {
            this.pdfBtn.addEventListener('click', () => this.exportPDF());
        }
        
        // Carica librerie necessarie
        this.loadDependencies();
    }
    
    loadDependencies() {
        // Assicurati che marked sia caricato
        if (typeof marked === 'undefined') {
            console.warn('Marked.js non ancora caricato, riprovo...');
            setTimeout(() => this.loadDependencies(), 100);
            return;
        }
        
        // Configura marked
        marked.setOptions({
            breaks: true,
            gfm: true,
            sanitize: false
        });
    }
    
    async sendMessage() {
        const content = this.input.value.trim();
        if (!content || this.isProcessing) return;
        
        this.isProcessing = true;
        this.sendBtn.disabled = true;
        
        // Aggiungi messaggio utente
        this.addMessage(content, 'user');
        this.input.value = '';
        
        // Aggiungi indicatore di digitazione
        const typingId = this.addTypingIndicator();
        
        try {
            // Invia richiesta al backend WordPress
            const formData = new FormData();
            formData.append('action', 'rentria_chat_process');
            formData.append('content', content);
            formData.append('nonce', this.config.nonce);
            
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            // Rimuovi indicatore digitazione
            this.removeTypingIndicator(typingId);
            
            if (data.success) {
                this.addMessage(data.data, 'bot');
            } else {
                this.addMessage('Mi dispiace, si è verificato un errore. Riprova più tardi.', 'bot');
            }
            
        } catch (error) {
            console.error('Errore:', error);
            this.removeTypingIndicator(typingId);
            this.addMessage('Errore di connessione. Verifica la tua connessione internet.', 'bot');
        } finally {
            this.isProcessing = false;
            this.sendBtn.disabled = false;
            this.input.focus();
        }
    }
    
    addMessage(content, type) {
        const messageId = `msg-${Date.now()}-${Math.random()}`;
        const messageDiv = document.createElement('div');
        messageDiv.className = `rentria-msg rentria-${type}`;
        messageDiv.id = messageId;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'rentria-msg-content';
        
        if (type === 'bot' && typeof marked !== 'undefined') {
            // Renderizza markdown per messaggi bot
            contentDiv.innerHTML = marked.parse(content);
        } else {
            contentDiv.textContent = content;
        }
        
        messageDiv.appendChild(contentDiv);
        
        // Aggiungi pulsanti azione per messaggi bot
        if (type === 'bot') {
            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'rentria-action-buttons';
            
            // Pulsante copia
            if (this.config.showCopy) {
                const copyBtn = this.createActionButton('copy', 'Copia', () => {
                    this.copyToClipboard(contentDiv.textContent, copyBtn);
                });
                actionsDiv.appendChild(copyBtn);
            }
            
            // Pulsante WhatsApp
            if (this.config.showWhatsApp) {
                const whatsappBtn = this.createActionButton('whatsapp', 'WhatsApp', () => {
                    this.shareWhatsApp(contentDiv.textContent);
                });
                actionsDiv.appendChild(whatsappBtn);
            }
            
            // Pulsante Telegram
            if (this.config.showTelegram) {
                const telegramBtn = this.createActionButton('telegram', 'Telegram', () => {
                    this.shareTelegram(contentDiv.textContent);
                });
                actionsDiv.appendChild(telegramBtn);
            }
            
            if (actionsDiv.children.length > 0) {
                messageDiv.appendChild(actionsDiv);
            }
        }
        
        this.messageList.appendChild(messageDiv);
        this.messages.push({ content, type, id: messageId });
        
        // Scroll automatico
        this.scrollToBottom();
        
        return messageId;
    }
    
    createActionButton(type, label, onClick) {
        const button = document.createElement('button');
        button.className = 'rentria-action-btn';
        
        let icon = '';
        switch(type) {
            case 'copy':
                icon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
                break;
            case 'whatsapp':
                icon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>';
                break;
            case 'telegram':
                icon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>';
                break;
        }
        
        button.innerHTML = `${icon}<span>${label}</span>`;
        button.addEventListener('click', onClick);
        
        return button;
    }
    
    async copyToClipboard(text, button) {
        const cleanText = this.stripMarkdown(text);
        const signature = `\n\nPiattaformaRentriFacile.it (${new Date().toLocaleDateString('it-IT')})`;
        const fullText = cleanText + signature;
        
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(fullText);
                this.showSuccess(button, 'Copiato!');
            } else {
                // Fallback
                const textArea = document.createElement('textarea');
                textArea.value = fullText;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                this.showSuccess(button, 'Copiato!');
            }
        } catch (err) {
            console.error('Errore copia:', err);
            alert('Errore durante la copia');
        }
    }
    
    shareWhatsApp(text) {
        const cleanText = this.stripMarkdown(text);
        const signature = `\n\nPiattaformaRentriFacile.it (${new Date().toLocaleDateString('it-IT')})`;
        const fullText = cleanText + signature;
        const encodedText = encodeURIComponent(fullText);
        
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const url = isMobile 
            ? `whatsapp://send?text=${encodedText}`
            : `https://web.whatsapp.com/send?text=${encodedText}`;
        
        window.open(url, '_blank');
    }
    
    shareTelegram(text) {
        const cleanText = this.stripMarkdown(text);
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        if (isMobile) {
            // Mobile: condividi messaggio
            const encodedText = encodeURIComponent(cleanText);
            window.open(`tg://msg_url?url=${window.location.href}&text=${encodedText}`, '_blank');
        } else {
            // Desktop: apri gruppo
            window.open('https://t.me/RentriFacile', '_blank');
        }
    }
    
    async exportPDF() {
        if (!this.messages.length) {
            alert('Nessun messaggio da esportare');
            return;
        }
        
        // Verifica che jsPDF sia caricato
        if (typeof window.jspdf === 'undefined' && typeof window.jsPDF === 'undefined') {
            alert('Libreria PDF non disponibile. Ricarica la pagina.');
            return;
        }
        
        try {
            // Trova il costruttore jsPDF
            const { jsPDF } = window.jspdf || window;
            const doc = new jsPDF();
            
            // Header
            doc.setFontSize(18);
            doc.text('Conversazione Assistente RentrIA', 20, 20);
            
            doc.setFontSize(10);
            doc.text(`Data: ${new Date().toLocaleString('it-IT')}`, 20, 30);
            
            // Contenuto
            let yPosition = 40;
            doc.setFontSize(12);
            
            this.messages.forEach((msg) => {
                const label = msg.type === 'user' ? 'Utente: ' : 'Assistente: ';
                const text = this.stripMarkdown(msg.content);
                const lines = doc.splitTextToSize(label + text, 170);
                
                // Controlla se serve nuova pagina
                if (yPosition + (lines.length * 5) > 270) {
                    doc.addPage();
                    yPosition = 20;
                }
                
                doc.text(lines, 20, yPosition);
                yPosition += lines.length * 5 + 5;
            });
            
            // Footer
            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(10);
                doc.text(`PiattaformaRentriFacile.it`, 20, 285);
                doc.text(`Pagina ${i} di ${pageCount}`, 170, 285);
            }
            
            // Salva
            const fileName = `conversazione-rentria-${new Date().toISOString().split('T')[0]}.pdf`;
            doc.save(fileName);
            
            this.showSuccess(this.pdfBtn, 'PDF Salvato!');
            
        } catch (error) {
            console.error('Errore generazione PDF:', error);
            alert('Errore durante la generazione del PDF');
        }
    }
    
    addTypingIndicator() {
        const indicatorId = `typing-${Date.now()}`;
        const indicatorDiv = document.createElement('div');
        indicatorDiv.className = 'rentria-msg rentria-bot';
        indicatorDiv.id = indicatorId;
        indicatorDiv.innerHTML = `
            <div class="rentria-typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        this.messageList.appendChild(indicatorDiv);
        this.scrollToBottom();
        return indicatorId;
    }
    
    removeTypingIndicator(indicatorId) {
        const indicator = document.getElementById(indicatorId);
        if (indicator) {
            indicator.remove();
        }
    }
    
    stripMarkdown(text) {
        // Rimuovi markdown base
        return text
            .replace(/#{1,6}\s*/g, '')
            .replace(/\*\*([^*]+)\*\*/g, '$1')
            .replace(/\*([^*]+)\*/g, '$1')
            .replace(/__([^_]+)__/g, '$1')
            .replace(/_([^_]+)_/g, '$1')
            .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
            .replace(/```[^`]*```/g, '')
            .replace(/`([^`]+)`/g, '$1')
            .replace(/^[-*+]\s+/gm, '• ')
            .replace(/^\d+\.\s+/gm, '• ')
            .replace(/^>\s+/gm, '')
            .replace(/\n{3,}/g, '\n\n');
    }
    
    showSuccess(button, message) {
        const originalContent = button.innerHTML;
        button.classList.add('success');
        button.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg><span>${message}</span>`;
        
        setTimeout(() => {
            button.classList.remove('success');
            button.innerHTML = originalContent;
        }, 2000);
    }
    
    scrollToBottom() {
        this.messageList.scrollTop = this.messageList.scrollHeight;
    }
}

// Auto-inizializzazione se non usando manager esterno
document.addEventListener('DOMContentLoaded', function() {
    // Il manager viene inizializzato dal PHP inline script
});