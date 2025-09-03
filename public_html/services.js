class ChatServices {
  constructor() {
    this.initCopyService();
    this.initWhatsAppService();
    this.initTelegramService();
    this.initPDFService();
  }

  initCopyService() {
    this.copyHandlers = new Map();
  }
  
  initWhatsAppService() {
    this.whatsappHandlers = new Map();
  }
  
  initTelegramService() {
    this.telegramHandlers = new Map();
  }
  
  initPDFService() {
    // PDF functionality moved to main HTML file
    this.pdfEnabled = typeof window.jsPDF !== 'undefined';
  }

  addShareButtons(messageElement, messageId) {
    console.log('addShareButtons chiamato per messaggio:', messageId);
    
    // Aggiungi tutti i pulsanti di condivisione
    this.addCopyButton(messageElement, messageId);
    this.addWhatsAppButton(messageElement, messageId);
    this.addTelegramButton(messageElement, messageId);
    
    // Aggiungi pulsante RENTRI se c'è analisi immagine
    console.log('Chiamando addRentriButton...');
    this.addRentriButton(messageElement, messageId);
  }

  addCopyButton(messageElement, messageId) {
    const existingBtn = messageElement.querySelector('.copy-btn');
    if (existingBtn) return;

    const copyBtn = document.createElement('button');
    copyBtn.className = 'copy-btn action-btn';
    copyBtn.innerHTML = `
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
      </svg>
    `;
    
    const copyHandler = async () => {
      const textContent = this.extractTextFromElement(messageElement);
      
      // Aggiungi la firma con data corrente
      const currentDate = new Date();
      const day = currentDate.getDate().toString().padStart(2, '0');
      const month = (currentDate.getMonth() + 1).toString().padStart(2, '0');
      const year = currentDate.getFullYear();
      const formattedDate = `${day}/${month}/${year}`;
      
      const textWithSignature = `${textContent}\n\nPiattaformaRentriFacile.it (${formattedDate})`;
      
      try {
        // Prova prima con l'API moderna
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(textWithSignature);
          this.showCopyFeedback(copyBtn, true);
        } else {
          // Fallback per contesti non sicuri o browser older
          const textArea = document.createElement('textarea');
          textArea.value = textWithSignature;
          textArea.style.position = 'fixed';
          textArea.style.left = '-999999px';
          textArea.style.top = '-999999px';
          document.body.appendChild(textArea);
          textArea.focus();
          textArea.select();
          
          try {
            const successful = document.execCommand('copy');
            if (successful) {
              this.showCopyFeedback(copyBtn, true);
            } else {
              throw new Error('execCommand failed');
            }
          } catch (err) {
            console.error('Fallback copy failed:', err);
            this.showCopyFeedback(copyBtn, false);
          } finally {
            document.body.removeChild(textArea);
          }
        }
      } catch (err) {
        console.error('Errore durante la copia:', err);
        
        // Ultimo tentativo con fallback
        const textArea = document.createElement('textarea');
        textArea.value = textWithSignature;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
          const successful = document.execCommand('copy');
          this.showCopyFeedback(copyBtn, successful);
        } catch (fallbackErr) {
          console.error('All copy methods failed:', fallbackErr);
          this.showCopyFeedback(copyBtn, false);
        } finally {
          document.body.removeChild(textArea);
        }
      }
    };

    copyBtn.addEventListener('click', copyHandler);
    this.copyHandlers.set(messageId, copyHandler);
    
    // Cerca o crea il wrapper
    let wrapper = messageElement.parentElement;
    if (!wrapper || !wrapper.classList.contains('msg-wrapper')) {
      wrapper = document.createElement('div');
      wrapper.className = 'msg-wrapper';
      messageElement.parentNode.insertBefore(wrapper, messageElement);
      wrapper.appendChild(messageElement);
    }
    
    // Cerca o crea il container dei pulsanti
    let btnContainer = wrapper.querySelector('.action-btn-container');
    if (!btnContainer) {
      btnContainer = document.createElement('div');
      btnContainer.className = 'action-btn-container';
      wrapper.appendChild(btnContainer);
    }
    
    btnContainer.appendChild(copyBtn);
  }
  
  addWhatsAppButton(messageElement, messageId) {
    const existingBtn = messageElement.querySelector('.whatsapp-btn');
    if (existingBtn) return;

    const whatsappBtn = document.createElement('button');
    whatsappBtn.className = 'whatsapp-btn action-btn';
    whatsappBtn.innerHTML = `
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.123-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
      </svg>
    `;
    
    const whatsappHandler = () => {
      let textContent = this.extractTextFromElement(messageElement);
      
      // Rimuovi eventuali riferimenti al sito già presenti nel testo
      // Pattern per trovare varie forme del link al sito (nuovo formato API)
      const patterns = [
        /Se hai bisogno di ulteriori dettagli contattaci[,:]?\s*,?\s*PiattaformaRentriFacile\.it/gi,
        /Se hai bisogno di ulteriori dettagli contattaci[,:]?\s*/gi,
        /https?:\/\/www\.PiattaformaRentriFacile\.it\s*\([^)]*\)/gi,
        /https?:\/\/PiattaformaRentriFacile\.it\s*\([^)]*\)/gi,
        /https?:\/\/www\.PiattaformaRentriFacile\.it/gi,
        /https?:\/\/PiattaformaRentriFacile\.it/gi,
        /www\.PiattaformaRentriFacile\.it/gi,
        /PiattaformaRentriFacile\.it/gi
      ];
      
      // Rimuovi tutti i pattern trovati
      patterns.forEach(pattern => {
        textContent = textContent.replace(pattern, '');
      });
      
      // Pulisci spazi e newline in eccesso alla fine
      textContent = textContent
        .replace(/\n{3,}/g, '\n\n')
        .replace(/\n+$/, '')
        .trim();
      
      // Aggiungi la firma con data corrente
      const currentDate = new Date();
      const day = currentDate.getDate().toString().padStart(2, '0');
      const month = (currentDate.getMonth() + 1).toString().padStart(2, '0');
      const year = currentDate.getFullYear();
      const formattedDate = `${day}/${month}/${year}`;
      
      const textWithSignature = `${textContent}\n\nPiattaformaRentriFacile.it (${formattedDate})`;
      
      // Codifica il testo per URL
      const encodedText = encodeURIComponent(textWithSignature);
      
      // Rileva se siamo su mobile o desktop
      const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
      
      let whatsappUrl;
      
      if (isMobile) {
        // Su mobile usa il protocollo whatsapp:// che apre direttamente l'app
        whatsappUrl = `whatsapp://send?text=${encodedText}`;
      } else {
        // Su desktop usa WhatsApp Web direttamente
        whatsappUrl = `https://web.whatsapp.com/send?text=${encodedText}`;
      }
      
      // Apri WhatsApp
      const newWindow = window.open(whatsappUrl, '_blank');
      
      // Se su desktop e la finestra non si apre (popup bloccato), prova con location
      if (!isMobile && !newWindow) {
        // Alternativa: copia negli appunti e mostra istruzioni
        this.copyToClipboardWithFeedback(textWithSignature, whatsappBtn);
      } else {
        // Feedback visivo normale
        this.showWhatsAppFeedback(whatsappBtn);
      }
    };

    whatsappBtn.addEventListener('click', whatsappHandler);
    this.whatsappHandlers.set(messageId, whatsappHandler);
    
    // Cerca o crea il wrapper
    let wrapper = messageElement.parentElement;
    if (!wrapper || !wrapper.classList.contains('msg-wrapper')) {
      wrapper = document.createElement('div');
      wrapper.className = 'msg-wrapper';
      messageElement.parentNode.insertBefore(wrapper, messageElement);
      wrapper.appendChild(messageElement);
    }
    
    // Cerca o crea il container dei pulsanti
    let btnContainer = wrapper.querySelector('.action-btn-container');
    if (!btnContainer) {
      btnContainer = document.createElement('div');
      btnContainer.className = 'action-btn-container';
      wrapper.appendChild(btnContainer);
    }
    
    btnContainer.appendChild(whatsappBtn);
  }

  addTelegramButton(messageElement, messageId) {
    const existingBtn = messageElement.querySelector('.telegram-btn');
    if (existingBtn) return;

    const telegramBtn = document.createElement('button');
    telegramBtn.className = 'telegram-btn action-btn';
    telegramBtn.setAttribute('type', 'button'); // 🔴 evita submit se dentro un <form>
    telegramBtn.innerHTML = `
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 
        9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 
        5.56-5.022c.24-.213-.054-.334-.373-.121l-6.869 4.326-2.96-.924c-.64-.203-.658-.64.135-.954l11.566-4.458c.538-.196 
        1.006.128.832.941z"/>
      </svg>
    `;

    const telegramHandler = (e) => {
      if (e) { e.preventDefault(); e.stopPropagation(); }

      // Rileva se siamo su mobile o desktop
      const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
      
      if (!isMobile) {
        // DESKTOP/BROWSER: Vai direttamente al gruppo Telegram
        const groupUrl = 'https://t.me/rentrifacile';
        try {
          window.location.assign(groupUrl);
        } catch {
          window.location.href = groupUrl;
        }
        this.showTelegramFeedback?.(e?.currentTarget || telegramBtn);
        return;
      }

      // MOBILE: Mantieni la logica esistente per condividere il testo

      // 1) Testo pulito + firma
      let textContent = this.extractTextFromElement
        ? this.extractTextFromElement(messageElement)
        : (messageElement.innerText || '').trim();

      const patterns = [
        /Se hai bisogno di ulteriori dettagli contattaci[,:]?\s*,?\s*PiattaformaRentriFacile\.it/gi,
        /Se hai bisogno di ulteriori dettagli contattaci[,:]?\s*/gi,
        /https?:\/\/www\.PiattaformaRentriFacile\.it\s*\([^)]*\)/gi,
        /https?:\/\/PiattaformaRentriFacile\.it\s*\([^)]*\)/gi,
        /https?:\/\/www\.PiattaformaRentriFacile\.it/gi,
        /https?:\/\/PiattaformaRentriFacile\.it/gi,
        /www\.PiattaformaRentriFacile\.it/gi,
        /PiattaformaRentriFacile\.it/gi
      ];
      patterns.forEach((p) => { textContent = textContent.replace(p, ''); });

      textContent = textContent.replace(/\n{3,}/g, '\n\n').replace(/\s+$/, '').trim();

      const d = new Date();
      const dd = String(d.getDate()).padStart(2, '0');
      const mm = String(d.getMonth() + 1).padStart(2, '0');
      const yyyy = d.getFullYear();
      const signature = `\n\nPiattaformaRentriFacile.it (${dd}/${mm}/${yyyy})`;

      // Limita la lunghezza per sicurezza URL
      const MAX = 1200;
      let composed = textContent + signature;
      if (composed.length > MAX) composed = composed.slice(0, MAX - 1) + '…';

      const publicUrl = messageElement.getAttribute('data-answer-url') || '';
      const tmeParams = [];
      if (publicUrl) tmeParams.push(`url=${encodeURIComponent(publicUrl)}`);
      tmeParams.push(`text=${encodeURIComponent(composed)}`);
      const tmeUrl = `https://t.me/share/url?${tmeParams.join('&')}`;

      // Fallback 1: protocol handler Telegram Desktop
      const tgUrl = `tg://msg?text=${encodeURIComponent(composed)}`;

      // Fallback 2: Telegram Web (senza precompilazione, ma con testo già in clipboard)
      const webUrl = `https://web.telegram.org/`;

      // Helper: prova ad aprire una destinazione, altrimenti richiama fallback()
      const tryNavigate = (url, fallback) => {
        let navigated = false;
        try {
          // Prova navigazione sincrona (user gesture)
          window.location.assign(url);
          navigated = true;
        } catch (_) {}
        // Se entro 1200ms non è cambiata la pagina (caso tipico di blocco),
        // proponi fallback.
        setTimeout(() => {
          if (!document.hidden && typeof fallback === 'function') fallback();
        }, 1200);
      };

      // Su mobile usa la logica esistente
      tryNavigate(tgUrl, async () => {
        try {
          if (navigator.clipboard) await navigator.clipboard.writeText(composed);
        } catch (_) {}
        window.location.assign(webUrl);
        this.showToast?.('Testo copiato. Incolla in Telegram Web.');
      });

      this.showTelegramFeedback?.(e?.currentTarget || telegramBtn);
    };

    telegramBtn.addEventListener('click', telegramHandler);
    this.telegramHandlers.set(messageId, telegramHandler);

    // Cerca o crea il wrapper
    let wrapper = messageElement.parentElement;
    if (!wrapper || !wrapper.classList.contains('msg-wrapper')) {
      wrapper = document.createElement('div');
      wrapper.className = 'msg-wrapper';
      messageElement.parentNode.insertBefore(wrapper, messageElement);
      wrapper.appendChild(messageElement);
    }
    
    // Cerca o crea il container dei pulsanti
    let btnContainer = wrapper.querySelector('.action-btn-container');
    if (!btnContainer) {
      btnContainer = document.createElement('div');
      btnContainer.className = 'action-btn-container';
      wrapper.appendChild(btnContainer);
    }
    
    btnContainer.appendChild(telegramBtn);
  }

  addRentriButton(messageElement, messageId) {
    const existingBtn = messageElement.querySelector('.rentri-btn');
    if (existingBtn) return;
    
    console.log('Aggiungo pulsante RENTRI per messaggio:', messageId);

    const rentriBtn = document.createElement('button');
    rentriBtn.className = 'rentri-btn action-btn';
    rentriBtn.setAttribute('type', 'button');
    rentriBtn.disabled = true; // Inizialmente disabilitato
    rentriBtn.style.opacity = '0.5'; // Visivamente disabilitato
    rentriBtn.innerHTML = `
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M4 12a8 8 0 0 1 8-8V2l4 4-4 4V8a6 6 0 0 0-6 6"/>
        <path d="M20 12a8 8 0 0 1-8 8v2l-4-4 4-4v2a6 6 0 0 0 6-6"/>
        <path d="M12 4V2l4 4-4 4V8"/>
      </svg>
    `;
    
    const rentriHandler = async () => {
      if (rentriBtn.disabled) {
        alert('⚠️ Effettua prima un\'analisi immagine per utilizzare RENTRI');
        return;
      }
      
      // Chiama la funzione di classificazione RENTRI dal main script
      if (window.requestRentriClassification) {
        window.requestRentriClassification();
      } else {
        alert('Funzione RENTRI non disponibile');
      }
    };

    rentriBtn.addEventListener('click', rentriHandler);
    
    // Cerca o crea il wrapper
    let wrapper = messageElement.parentElement;
    if (!wrapper || !wrapper.classList.contains('msg-wrapper')) {
      wrapper = document.createElement('div');
      wrapper.className = 'msg-wrapper';
      messageElement.parentNode.insertBefore(wrapper, messageElement);
      wrapper.appendChild(messageElement);
    }
    
    // Cerca o crea il container dei pulsanti
    let btnContainer = wrapper.querySelector('.action-btn-container');
    if (!btnContainer) {
      btnContainer = document.createElement('div');
      btnContainer.className = 'action-btn-container';
      wrapper.appendChild(btnContainer);
    }
    
    btnContainer.appendChild(rentriBtn);
    
    // Rendi il pulsante accessibile globalmente per attivazione
    rentriBtn.setAttribute('data-message-id', messageId);
  }
  
  // Funzione globale per attivare pulsanti RENTRI dopo analisi
  enableRentriButtons() {
    const rentriButtons = document.querySelectorAll('.rentri-btn');
    rentriButtons.forEach(btn => {
      btn.disabled = false;
      btn.style.opacity = '1';
      console.log('Pulsante RENTRI attivato per messaggio:', btn.getAttribute('data-message-id'));
    });
  }

  showWhatsAppFeedback(button) {
    const originalHTML = button.innerHTML;
    
    button.classList.add('success');
    button.innerHTML = `
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
      <span class="btn-text">Inviato!</span>
    `;
    
    setTimeout(() => {
      button.classList.remove('success');
      button.innerHTML = originalHTML;
    }, 2000);
  }
  
  showTelegramFeedback(button) {
    const originalHTML = button.innerHTML;
    
    button.classList.add('success');
    button.innerHTML = `
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
      <span class="btn-text">Condiviso!</span>
    `;
    
    setTimeout(() => {
      button.classList.remove('success');
      button.innerHTML = originalHTML;
    }, 2000);
  }
  
  showTelegramCopyFeedback(button) {
    const originalHTML = button.innerHTML;
    
    button.classList.add('success');
    button.innerHTML = `
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
      </svg>
      <span class="btn-text">Copiato! Apri Telegram</span>
    `;
    
    setTimeout(() => {
      button.classList.remove('success');
      button.innerHTML = originalHTML;
    }, 3000);
  }
  
  copyToClipboardWithFeedback(text, button) {
    // Copia il testo negli appunti
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
      document.execCommand('copy');
      // Mostra feedback speciale
      const originalHTML = button.innerHTML;
      button.classList.add('success');
      button.innerHTML = `
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
        </svg>
        <span class="btn-text">Copiato! Incolla in WhatsApp</span>
      `;
      
      setTimeout(() => {
        button.classList.remove('success');
        button.innerHTML = originalHTML;
      }, 3000);
    } catch (err) {
      console.error('Errore copia per WhatsApp:', err);
    } finally {
      document.body.removeChild(textArea);
    }
  }
  
  copyToClipboardForTelegram(text, button) {
    // Copia il testo negli appunti
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
      document.execCommand('copy');
      // Mostra feedback speciale per Telegram
      const originalHTML = button.innerHTML;
      button.classList.add('success');
      button.innerHTML = `
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
        </svg>
        <span class="btn-text">Copiato! Incolla in Telegram</span>
      `;
      
      setTimeout(() => {
        button.classList.remove('success');
        button.innerHTML = originalHTML;
      }, 4000);
    } catch (err) {
      console.error('Errore copia per Telegram:', err);
    } finally {
      document.body.removeChild(textArea);
    }
  }

  extractTextFromElement(element) {
    const clone = element.cloneNode(true);
    
    // Rimuovi i pulsanti
    const buttons = clone.querySelectorAll('.action-btn-container, .action-btn');
    buttons.forEach(btn => btn.remove());
    
    // Funzione ricorsiva per estrarre il testo con formattazione
    const extractText = (node, isListItem = false) => {
      let result = '';
      
      for (let child of node.childNodes) {
        if (child.nodeType === Node.TEXT_NODE) {
          result += child.textContent;
        } else if (child.nodeType === Node.ELEMENT_NODE) {
          const tag = child.tagName.toLowerCase();
          
          switch(tag) {
            case 'br':
              result += '\n';
              break;
              
            case 'p':
            case 'div':
              const innerText = extractText(child);
              if (innerText) {
                // Aggiungi doppio a capo prima del paragrafo se non siamo all'inizio
                if (result && !result.endsWith('\n\n')) {
                  result += result.endsWith('\n') ? '\n' : '\n\n';
                }
                result += innerText;
                // Aggiungi doppio a capo dopo il paragrafo
                if (!innerText.endsWith('\n')) {
                  result += '\n\n';
                }
              }
              break;
              
            case 'ul':
            case 'ol':
              const listText = extractText(child, true);
              if (listText) {
                if (result && !result.endsWith('\n')) {
                  result += '\n';
                }
                result += listText;
                if (!listText.endsWith('\n')) {
                  result += '\n';
                }
              }
              break;
              
            case 'li':
              const liText = extractText(child);
              if (liText) {
                result += '• ' + liText.trim() + '\n';
              }
              break;
              
            case 'strong':
            case 'b':
              result += '*' + extractText(child) + '*';
              break;
              
            case 'em':
            case 'i':
              result += '_' + extractText(child) + '_';
              break;
              
            case 'code':
              // Se è inline code
              if (!child.parentElement || child.parentElement.tagName !== 'PRE') {
                result += '`' + child.textContent + '`';
              } else {
                result += child.textContent;
              }
              break;
              
            case 'pre':
              const codeText = extractText(child);
              if (codeText) {
                if (result && !result.endsWith('\n')) {
                  result += '\n';
                }
                result += '```\n' + codeText.trim() + '\n```\n';
              }
              break;
              
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
              const headerText = extractText(child);
              if (headerText) {
                if (result && !result.endsWith('\n')) {
                  result += '\n';
                }
                result += '*' + headerText.trim() + '*\n\n';
              }
              break;
              
            case 'blockquote':
              const quoteText = extractText(child);
              if (quoteText) {
                if (result && !result.endsWith('\n')) {
                  result += '\n';
                }
                // Aggiungi > all'inizio di ogni riga
                const quotedLines = quoteText.trim().split('\n').map(line => '> ' + line).join('\n');
                result += quotedLines + '\n\n';
              }
              break;
              
            case 'a':
              const linkText = extractText(child);
              const href = child.getAttribute('href');
              if (href && linkText) {
                result += linkText + ' (' + href + ')';
              } else {
                result += linkText;
              }
              break;
              
            case 'hr':
              result += '\n---\n';
              break;
              
            default:
              result += extractText(child, isListItem);
          }
        }
      }
      
      return result;
    };
    
    let text = extractText(clone);
    
    // Pulisci spazi multipli e newline eccessive
    text = text
      .replace(/\n{3,}/g, '\n\n')  // Max 2 newline consecutive
      .replace(/[ \t]+/g, ' ')      // Spazi multipli -> singolo spazio
      .replace(/\n[ \t]+/g, '\n')   // Rimuovi spazi all'inizio delle righe
      .trim();
    
    return text;
  }

  showCopyFeedback(button, success) {
    const originalHTML = button.innerHTML;
    
    if (success) {
      button.classList.add('success');
      button.innerHTML = `
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <span class="btn-text">Copiato!</span>
      `;
    } else {
      button.classList.add('error');
      button.innerHTML = `
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
        <span class="btn-text">Errore</span>
      `;
    }
    
    setTimeout(() => {
      button.classList.remove('success', 'error');
      button.innerHTML = originalHTML;
    }, 2000);
  }

  cleanup() {
    this.copyHandlers.clear();
  }
}

const chatServices = new ChatServices();

const addCopyStyles = () => {
  if (document.getElementById('chat-services-styles')) return;
  
  const styles = document.createElement('style');
  styles.id = 'chat-services-styles';
  styles.textContent = `
    .msg-wrapper {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      margin-bottom: 12px;
    }
    
    .msg-wrapper .msg {
      margin-bottom: 4px;
    }
    
    .action-btn-container {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 4px;
      opacity: 0;
      transition: opacity 0.2s ease;
    }
    
    .msg-wrapper:hover .action-btn-container {
      opacity: 1;
    }
    
    .action-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      background: #f0f0f0;
      border: 1px solid #ddd;
      border-radius: 12px;
      color: #666;
      font-size: 11px;
      font-family: 'Space Grotesk', sans-serif;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .action-btn:hover {
      background: #e8e8e8;
      color: #333;
      border-color: #ccc;
    }
    
    .action-btn:active {
      transform: scale(0.95);
    }
    
    .action-btn.success {
      background: #d4edda;
      color: #155724;
      border-color: #c3e6cb;
    }
    
    .action-btn.error {
      background: #f8d7da;
      color: #721c24;
      border-color: #f5c6cb;
    }
    
    .action-btn svg {
      width: 14px;
      height: 14px;
    }
    
    .whatsapp-btn:hover {
      background: #25d366;
      color: white;
      border-color: #25d366;
    }
    
    .whatsapp-btn:hover svg {
      fill: white;
    }
    
    .whatsapp-btn.success {
      background: #25d366;
      color: white;
      border-color: #25d366;
    }
    
    .telegram-btn:hover {
      background: #0088cc;
      color: white;
      border-color: #0088cc;
    }
    
    .telegram-btn:hover svg {
      fill: white;
    }
    
    .telegram-btn.success {
      background: #0088cc;
      color: white;
      border-color: #0088cc;
    }
    
    .rentri-btn:hover {
      background: #1F7138;
      color: white;
      border-color: #1F7138;
    }
    
    .rentri-btn:hover svg {
      stroke: white;
    }
    
    .rentri-btn.success {
      background: #1F7138;
      color: white;
      border-color: #1F7138;
    }
    
    .rentri-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      background: #f8f9fa;
      color: #6c757d;
      border-color: #dee2e6;
    }
    
    #pdf-btn.success {
      background: #d4edda !important;
      color: #155724 !important;
    }
    
    @media (max-width: 600px) {
      .action-btn-container {
        opacity: 1;
      }
    }
  `;
  document.head.appendChild(styles);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', addCopyStyles);
} else {
  addCopyStyles();
}

window.chatServices = chatServices;