@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Log;
@endphp
<x-filament::page>
    <style>
        .container {
            display: flex;
            height: 80vh;
            gap: 16px;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            width: 35%;
            background: #f3f9f1;
            border-radius: 8px;
            overflow-y: auto;
            
        }

        .sidebar-header {
            font-size: 16px;
            font-weight: 600;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            background: #0768a8;
            position: sticky;
            top: 0;
            z-index: 10;
            color: white;
            text-align: center;
        }

        .conversation-item {
            padding: 8px 12px;
            cursor: pointer;
            background: transparent;
            transition: all 0.3s ease;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .conversation-item:hover {
            background: #e2e8f0;
        }

        .conversation-item.selected {
            background: #c5f598;
            color: #ffffff;
            border-radius: 4px;
            margin: 4px 8px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .sender-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 4px;
        }

        .sender-name {
            color: #102b55;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sender-name.unread {
            font-weight: bold;
            color: #0a0eff
        }
        .page-name {
            color: rgb(206, 24, 0);
        }
        .last-message {
            font-size: 12px;
            color: #255ead;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            word-break: break-word;
            max-width: 50ch;
        }

        .last-message.unread {
            fontStored: true;
            font-weight: bold;
        }

        .timestamp {
            font-size: 12px;
            color: #2f65b1;
        }

        .message-container {
            flex: 1;
            background: #c5f598;
            padding: 16px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .message-header {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #59db0e;
        }

        .message-list {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-right: 8px;
            position: relative;
            background: #c5f598;
        }

        .message {
            max-width: 60%;
            padding: 8px 12px;
            border-radius: 12px;
            font-size: 14px;
            white-space: normal;
            word-break: break-word;
            max-width: 50ch;
        }

        .message.received {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #e2e8f0;  
        }

        .message.sent {
            background: #5277f3;
            color: #ffffff;
        }

        .message-time {
            font-size: 12px;
            margin-top: 4px;
            text-align: right;
            opacity: 0.8;
        }

        .message-time.received {
            color: #1e293b;
        }

        .message-time.sent {
            color: #ffffff;
        }

        .message-placeholder {
            text-align: center;
            color: #1e40af;
            font-size: 16px;
            font-weight: 500;
        }

        .message-list .flex {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .message-list .justify-start .avatar {
            order: -1;
        }

        .message-list .justify-end .avatar {
            order: 1;
        }

        .reply-form {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #ffffff;
            padding: 8px 12px;
            border-radius: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .reply-form:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .reply-input {
            flex: 1;
            border: none;
            border-radius: 20px;
            padding: 10px 16px;
            font-size: 14px;
            outline: none;
            background: transparent;
            transition: all 0.3s ease;
            line-height: 1.5;
        }

        .reply-input:focus {
            background: #f1f5f9;
        }

        .send-button {
            background: #3b82f6;
            color: #ffffff;
            padding: 6px;
            border-radius: 50%;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            width: 32px;
            height: 32px;
        }

        .send-button:hover {
            background: #1d4ed8;
            transform: scale(1.05);
        }

        .send-button:disabled {
            background: #a3bffa;
            cursor: not-allowed;
        }

        .spinner {
            animation: spin 1s linear infinite;
            display: inline-block;
            width: 16px;
            height: 16px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .upload-button,
        .emoji-button {
            padding: 8px;
            cursor: pointer;
            font-size: 18px;
            border-radius: 50%;
            transition: all 0.3s ease;
            position: relative;
        }

        .upload-button:hover,
        .emoji-button:hover {
            background: #e2e8f0;
            transform: scale(1.1);
        }

        .like-button {
            cursor: pointer;
            width: 24px;
            height: 24px;
            transition: all 0.3s ease;
        }

        .like-button:hover {
            background: #e2e8f0;
            transform: scale(1.1);
        }

        .emoji-picker {
            position: absolute;
            bottom: 50px;
            left: 10px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            display: none;
            z-index: 100;
            max-width: 300px;
            flex-wrap: wrap;
            gap: 5px;
            max-height: 200px;
            overflow-y: auto;
        }

        .emoji-picker.show {
            display: flex;
        }

        .emoji-option {
            cursor: pointer;
            font-size: 24px;
            padding: 5px;
        }

        .emoji-option:hover {
            background: #e2e8f0;
            border-radius: 4px;
        }

        .message-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            transition: transform 0.2s ease;
        }

        .message-image:hover {
            transform: scale(1.05);
        }

        .message-video {
            width: 150px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
        }

        .message-audio {
            margin-top: 4px;
        }

        .message-document {
            display: flex;
            align-items: center;
            padding: 8px;
            background: #f1f5f9;
            border-radius: 8px;
            max-width: 250px;
        }

        .message-document a {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #3b82f6;
            text-decoration: none;
        }

        .message-document a:hover {
            text-decoration: underline;
        }

        .message-document svg {
            flex-shrink: 0;
        }

        .attachment-fallback {
            font-style: italic;
            color: #64748b;
            margin-top: 4px;
        }

        .video-error {
            font-style: italic;
            color: #ff4444;
            margin-top: 4px;
        }

        .image-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 4px;
        }

        .preview-images {
            display: flex;
            gap: 4px;
            margin-bottom: 4px;
        }

        .preview-image,
        .preview-video,
        .preview-document {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            position: relative;
        }

        .preview-document {
            display: flex;
            align-items: center;
            padding: 6px;
            background: #f1f5f9;
            border-radius: 4px;
            font-size: 12px;
            max-width: 150px;
            position: relative;
        }

        .preview-document svg {
            flex-shrink: 0;
        }

        .remove-preview {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 16px;
            height: 16px;
            background: #ff4444;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            cursor: pointer;
            border: 1px solid #fff;
        }

        .remove-preview:hover {
            background: #cc0000;
        }

        /* Updated CSS for the "Đặt ngay" button */
        .generic-template a {
            background: #28a745 !important; /* Green color */
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            display: block; /* Makes the button a block element to center it */
            margin: 4px auto; /* Centers the button horizontally */
            text-align: center; /* Centers the text inside the button */
            width: fit-content; /* Ensures the button width fits its content */
        }

        /* Thêm CSS cho nút micro và xem trước âm thanh */
        .microphone-button {
            padding: 8px;
            cursor: pointer;
            font-size: 18px;
            border-radius: 50%;
            transition: all 0.3s ease;
            position: relative;
        }

        .microphone-button:hover {
            background: #e2e8f0;
            transform: scale(1.1);
        }

        .microphone-button.recording {
            background: #ff4444;
            color: #ffffff;
        }

        .preview-audio {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px;
            background: #f1f5f9;
            border-radius: 4px;
            max-width: 150px;
            position: relative;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                height: auto;
            }

            .sidebar {
                width: 100%;
                max-height: 40vh;
            }

            .message-container {
                width: 100%;
            }

            .reply-form {
                padding: 6px 10px;
            }

            .send-button {
                width: 28px;
                height: 28px;
                padding: 4px;
            }

            .message-image,
            .message-video {
                width: 80px;
                height: 80px;
            }

            .message-document {
                max-width: 200px;
            }

            .preview-image,
            .preview-video,
            .preview-document {
                width: 40px;
                height: 40px;
            }

            .emoji-picker {
                max-width: 100%;
                left: 0;
                bottom: 60px;
            }

            .like-button {
                width: 20px;
                height: 20px;
            }
        }
    </style>

    <script>
        function scrollToBottom() {
            const messageList = document.querySelector('.message-list');
            if (messageList) {
                requestAnimationFrame(() => {
                    messageList.scrollTo({
                        top: messageList.scrollHeight,
                        behavior: 'smooth'
                    });
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            scrollToBottom();
            setupImagePreview();
            setupFormValidation();
            setupEmojiPicker();
            setupLikeButton();
            setupMicrophone();
        });

        Livewire.on('conversationSelected', () => {
            scrollToBottom();
        });

        document.addEventListener('livewire:updated', () => {
            scrollToBottom();
        });

        function startPolling() {
            let isTyping = false;
            let isSending = false;
            let isInteracting = false;
            const messageInput = document.querySelector('.reply-input');
            const fileInput = document.querySelector('input[type="file"][wire\\:model="uploadFile"]');

            messageInput.addEventListener('input', () => {
                isTyping = true;
                isInteracting = true;
                setTimeout(() => {
                    isTyping = false;
                    isInteracting = false;
                }, 5000);
            });

            fileInput.addEventListener('change', () => {
                isInteracting = true;
                @this.set('hasPendingUpload', true);
            });

            setInterval(() => {
                if (!isTyping && !isSending && !isInteracting) {
                    @this.call('pollMessages');
                }
            }, 15000);

            return {
                setSending: (state) => {
                    isSending = state;
                },
                setInteracting: (state) => {
                    isInteracting = state;
                }
            };
        }

        function setupImagePreview() {
            const input = document.querySelector('input[type="file"][wire\\:model="uploadFile"]');
            const previewContainer = document.querySelector('.preview-images');
            const form = document.querySelector('.reply-form');

            if (!input || !previewContainer || !form) return;

            input.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                previewContainer.innerHTML = '';

                files.forEach((file, index) => {
                    const isImage = file.type.startsWith('image/');
                    const isVideo = file.type.startsWith('video/');
                    const isAudio = file.type.startsWith('audio/');
                    const isDocument = file.type.includes('pdf') || 
                                      file.type.includes('msword') || 
                                      file.type.includes('wordprocessingml') || 
                                      file.type.includes('ms-excel') || 
                                      file.type.includes('spreadsheetml') || 
                                      file.type.includes('ms-powerpoint') || 
                                      file.type.includes('presentationml');

                    let element;

                    if (isImage || isVideo) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            element = isImage ? document.createElement('img') : document.createElement('video');
                            element.src = e.target.result;
                            element.className = isImage ? 'preview-image' : 'preview-video';
                            if (isVideo) {
                                element.muted = true;
                                element.setAttribute('controls', '');
                            }
                            appendPreviewElement(element, file, index);
                        };
                        reader.readAsDataURL(file);
                    } else if (isAudio || isDocument) {
                        element = document.createElement('div');
                        element.className = isAudio ? 'preview-audio' : 'preview-document';
                        element.innerHTML = `
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="${isAudio ? 'M9 19V6l6-3v16l-6-3zm6 0V6l6-3v16l-6-3z' : 'M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'}" />
                                </svg>
                                <span style="max-width: 100px; overflow: hidden; text-overflow: ellipsis;">${file.name}</span>
                            </span>
                        `;
                        appendPreviewElement(element, file, index);
                    } else {
                        element = document.createElement('div');
                        element.className = 'preview-document';
                        element.innerHTML = `
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span style="max-width: 100px; overflow: hidden; text-overflow: ellipsis;">${file.name}</span>
                            </span>
                        `;
                        appendPreviewElement(element, file, index);
                    }
                });

                @this.set('uploadFile', files);
                updateSendButtonState();
            });

            function appendPreviewElement(element, file, index) {
                const removeBtn = document.createElement('span');
                removeBtn.className = 'remove-preview';
                removeBtn.innerHTML = '×';
                removeBtn.onclick = () => {
                    element.remove();
                    removeBtn.remove();
                    const dataTransfer = new DataTransfer();
                    const remainingFiles = Array.from(input.files).filter((_, i) => i !== index);
                    remainingFiles.forEach(f => dataTransfer.items.add(f));
                    input.files = dataTransfer.files;
                    @this.set('uploadFile', input.files);
                    updateSendButtonState();
                };

                previewContainer.appendChild(element);
                previewContainer.appendChild(removeBtn);
            }

            form.addEventListener('submit', (e) => {
                e.preventDefault();
            });
        }

        function setupFormValidation() {
            const form = document.querySelector('.reply-form');
            const messageInput = document.querySelector('.reply-input');
            const fileInput = document.querySelector('input[type="file"][wire\\:model="uploadFile"]');
            const sendButton = document.querySelector('.send-button');
            const previewContainer = document.querySelector('.preview-images');

            if (!form || !messageInput || !fileInput || !sendButton || !previewContainer) return;

            const pollingControl = startPolling();

            function updateSendButtonState() {
                const message = messageInput.value.trim();
                const files = fileInput.files;
                const conversationSelected = @entangle('selectedConversationId').value !== null;
                sendButton.disabled = (!message && files.length === 0) || !conversationSelected; // Sửa lỗi cú pháp !0772 thành !message
            }

            function sendMessage() {
                const message = messageInput.value.trim();
                const files = fileInput.files;
                const conversationSelected = @entangle('selectedConversationId').value !== null;

                if (!message && files.length === 0 || !conversationSelected) return;

                console.log('Sending message:', {
                    message,
                    files: files.length
                });

                pollingControl.setSending(true);
                pollingControl.setInteracting(false);

                @this.set('replyMessage', message).then(() => {
                    @this.call('sendReply').then(() => {
                        console.log('Message sent successfully');
                        messageInput.value = '';
                        previewContainer.innerHTML = '';
                        fileInput.value = '';
                        @this.set('uploadFile', []);
                        updateSendButtonState();
                        pollingControl.setSending(false);
                    }).catch((error) => {
                        console.error('Error sending message:', error);
                        alert('Có lỗi xảy ra khi gửi tin nhắn. Vui lòng thử lại.');
                        pollingControl.setSending(false);
                    });
                });
            }

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                sendMessage();
            });

            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            messageInput.addEventListener('input', updateSendButtonState);
            fileInput.addEventListener('change', updateSendButtonState);

            Livewire.on('updated', () => {
                updateSendButtonState();
            });
        }

        function setupEmojiPicker() {
            const emojiButton = document.querySelector('.emoji-button');
            const emojiPicker = document.querySelector('.emoji-picker');
            const messageInput = document.querySelector('.reply-input');

            if (!emojiButton || !emojiPicker || !messageInput) return;

            emojiButton.addEventListener('click', () => {
                emojiPicker.classList.toggle('show');
            });

            emojiPicker.addEventListener('click', (e) => {
                if (e.target.classList.contains('emoji-option')) {
                    messageInput.value += e.target.textContent;
                    emojiPicker.classList.remove('show');
                    messageInput.focus();
                    @this.set('replyMessage', messageInput.value);
                    updateSendButtonState();
                }
            });

            document.addEventListener('click', (e) => {
                if (!emojiPicker.contains(e.target) && !emojiButton.contains(e.target)) {
                    emojiPicker.classList.remove('show');
                }
            });
        }

        function setupLikeButton() {
            const likeButton = document.querySelector('.like-button');
            const previewContainer = document.querySelector('.preview-images');

            if (!likeButton || !previewContainer) return;

            likeButton.addEventListener('click', () => {
                const conversationSelected = @entangle('selectedConversationId').value !== null;
                if (!conversationSelected) {
                    alert('Vui lòng chọn một hội thoại trước khi gửi Like.');
                    return;
                }

                const pollingControl = startPolling();
                pollingControl.setSending(true);
                pollingControl.setInteracting(false);

                @this.set('replyMessage', '👍').then(() => {
                    @this.call('sendReply').then(() => {
                        console.log('Like sent successfully');
                        previewContainer.innerHTML = '';
                        pollingControl.setSending(false);
                    }).catch((error) => {
                        console.error('Error sending like:', error);
                        alert('Có lỗi xảy ra khi gửi Like. Vui lòng thử lại.');
                        pollingControl.setSending(false);
                    });
                });
            });
        }

        // Thêm hàm xử lý micro với kiểm tra quyền
        let mediaRecorder = null;
        let audioChunks = [];

        async function checkMicrophonePermission() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                stream.getTracks().forEach(track => track.stop());
                return true;
            } catch (err) {
                alert('Không thể truy cập micro. Vui lòng cấp quyền trong cài đặt trình duyệt hoặc nhấp "OK" trên thông báo.');
                return false;
            }
        }

        function setupMicrophone() {
            const micButton = document.querySelector('.microphone-button');
            const fileInput = document.querySelector('input[type="file"][wire\\:model="uploadFile"]');
            const previewContainer = document.querySelector('.preview-images');

            if (!micButton || !fileInput || !previewContainer) return;

            micButton.addEventListener('click', async () => {
                if (micButton.classList.contains('recording')) {
                    mediaRecorder.stop();
                    micButton.classList.remove('recording');
                } else {
                    if (await checkMicrophonePermission()) {
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            mediaRecorder = new MediaRecorder(stream);
                            audioChunks = [];

                            mediaRecorder.ondataavailable = (e) => {
                                audioChunks.push(e.data);
                            };

                            mediaRecorder.onstop = () => {
                                const audioBlob = new Blob(audioChunks, { type: 'audio/mpeg' });
                                const audioFile = new File([audioBlob], `voice-message-${Date.now()}.mp3`, { type: 'audio/mpeg' });

                                const dataTransfer = new DataTransfer();
                                dataTransfer.items.add(audioFile);
                                fileInput.files = dataTransfer.files;
                                @this.set('uploadFile', [audioFile]);

                                previewContainer.innerHTML = '';
                                const previewElement = document.createElement('div');
                                previewElement.className = 'preview-audio';
                                previewElement.innerHTML = `
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M9 19V6l6-3v16l-6-3zm6 0V6l6-3v16l-6-3z" />
                                    </svg>
                                    <span style="max-width: 100px; overflow: hidden; text-overflow: ellipsis;">${audioFile.name}</span>
                                `;
                                const removeBtn = document.createElement('span');
                                removeBtn.className = 'remove-preview';
                                removeBtn.innerHTML = '×';
                                removeBtn.onclick = () => {
                                    previewElement.remove();
                                    removeBtn.remove();
                                    fileInput.value = '';
                                    @this.set('uploadFile', []);
                                    updateSendButtonState();
                                };
                                previewContainer.appendChild(previewElement);
                                previewContainer.appendChild(removeBtn);

                                stream.getTracks().forEach(track => track.stop());
                            };

                            mediaRecorder.start();
                            micButton.classList.add('recording');
                        } catch (err) {
                            console.error('Lỗi khi ghi âm:', err);
                            alert('Có lỗi xảy ra khi ghi âm. Vui lòng thử lại.');
                        }
                    }
                }
            });
        }
    </script>

    <div class="container">
        <div class="sidebar">
            <h2 class="sidebar-header">
                Danh sách hội thoại
            </h2>

            @forelse ($this->messages as $conv)
                <div wire:key="conv-{{ $conv['conversation_id'] }}"
                    class="conversation-item {{ $selectedConversationId === $conv['conversation_id'] ? 'selected' : '' }}"
                    style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; gap: 10px; cursor: pointer; width: 100%;"
                        wire:click="$set('selectedConversationId', '{{ $conv['conversation_id'] }}'); $dispatch('conversationSelected')">
                        <img src="{{ $conv['avatar_url'] }}" alt="{{ $conv['sender'] }}'s avatar" class="avatar"
                            loading="lazy" />
                        <div class="sender-info" style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span class="sender-name {{ $conv['unread'] ? 'unread' : '' }}">
                                    {{ $conv['sender'] }}
                                </span>

                                <span class="timestamp">
                                    {{ \Carbon\Carbon::parse($conv['last_message_time'])->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}
                                </span>
                            </div>
                            <span class="page-name">
                                {{ $conv['page_name'] }}
                            </span>
                            <span class="last-message {{ $conv['unread'] ? 'unread' : '' }}">
                                {{ Str::limit($conv['last_message'], 30) }}
                            </span>
                        </div>
                    </div>

                    <div x-data="{ starred: localStorage.getItem('star-{{ $conv['conversation_id'] }}') === 'true' }"
                        @click.stop="starred = !starred; localStorage.setItem('star-{{ $conv['conversation_id'] }}', starred)"
                        class="cursor-pointer text-2xl px-2 transition transform duration-200 ease-in-out"
                        :class="{
                            'text-yellow-500 drop-shadow-md scale-110': starred,
                            'text-gray-300 hover:text-yellow-400 hover:scale-110': !starred
                        }"
                        title="Đánh dấu khách đã đặt">
                        <span x-text="starred ? '★' : '☆'"></span>
                    </div>
                </div>
            @empty
                <p class="message-placeholder classifying">Không có hội thoại nào.</p>
            @endforelse
        </div>

        <div class="message-container">
            @if ($this->selectedConversation)
                <div class="message-list">
                    <div
                        style="position: sticky; top: 0; z-index: 10; background-color: rgb(90, 100, 241); color: white; padding: 10px 16px; border-radius: 8px 8px 0 0;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <img src="{{ $this->selectedConversation['avatar_url'] }}"
                                alt="{{ $this->selectedConversation['sender'] }}'s avatar"
                                style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid white;"
                                loading="lazy">
                            <div style="flex: 1;">
                                <span
                                    style="font-size: 16px; font-weight: bold;">{{ $this->selectedConversation['sender'] }}</span>
                                <div class="page-name" style="color: #ffffff;">Trang:
                                    {{ $this->selectedConversation['page_name'] }}</div>
                            </div>
                        </div>
                    </div>

                    @foreach (collect($this->selectedConversation['messages'])->sortBy('created_time') as $msg)
                        @php
                            $platformAccount = App\Models\PlatformAccount::where(
                                'page_id',
                                $this->selectedConversation['page_id'],
                            )->first();
                            $isMine = strtolower(trim($msg['from'])) === strtolower(trim($platformAccount->name));
                            $guestAvatar = $this->selectedConversation['avatar_url'];

                            if (isset($msg['attachments'])) {
                                Log::info('Attachments structure for message', [
                                    'message_id' => $msg['id'] ?? $msg['message_id'],
                                    'attachments' => $msg['attachments'],
                                    'has_data' => isset($msg['attachments']['data']),
                                    'data_count' => isset($msg['attachments']['data'])
                                        ? count($msg['attachments']['data'])
                                        : 0,
                                    'first_attachment' => isset($msg['attachments']['data'][0])
                                        ? $msg['attachments']['data'][0]
                                        : 'No first attachment',
                                    'url' => isset($msg['attachments']['data'][0]['payload']['url'])
                                        ? $msg['attachments']['data'][0]['payload']['url']
                                        : (isset($msg['attachments']['data'][0]['url'])
                                            ? $msg['attachments']['data'][0]['url']
                                            : 'No URL'),
                                ]);
                            }
                        @endphp

                        <div class="w-full flex {{ $isMine ? 'justify-end' : 'justify-start' }} items-start gap-3">
                            @if (!$isMine)
                                <img src="{{ asset('images/avatar.png') }}"
                                    alt="{{ $this->selectedConversation['sender'] }}'s avatar" class="avatar"
                                    loading="lazy" />
                                   
                                <div class="message received">
                                    @if (isset($msg['message']) && !empty($msg['message']))
                                        <div>{{ $msg['message'] }}</div>
                                    @endif
                                    @if (isset($msg['attachments']['data']) && !empty($msg['attachments']['data']))
                                        <div class="image-gallery">
                                            @foreach ($msg['attachments']['data'] as $attachment)
                                                @php
                                                    $fileUrl = $attachment['payload']['url'] ?? ($attachment['url'] ?? ($attachment['file_url'] ?? ''));
                                                    $isDocument = isset($attachment['mime_type']) && (
                                                        strpos($attachment['mime_type'], 'pdf') !== false ||
                                                        strpos($attachment['mime_type'], 'msword') !== false ||
                                                        strpos($attachment['mime_type'], 'wordprocessingml') !== false ||
                                                        strpos($attachment['mime_type'], 'ms-excel') !== false ||
                                                        strpos($attachment['mime_type'], 'spreadsheetml') !== false ||
                                                        strpos($attachment['mime_type'], 'ms-powerpoint') !== false ||
                                                        strpos($attachment['mime_type'], 'presentationml') !== false
                                                    );
                                                @endphp
                                                @if ((isset($attachment['type']) && $attachment['type'] === 'image') || 
                                                     (isset($attachment['mime_type']) && strpos($attachment['mime_type'], 'image') === 0))
                                                    @php
                                                        $imageUrl = $attachment['payload']['url'] ?? 
                                                                   ($attachment['url'] ?? 
                                                                   ($attachment['file_url'] ?? 
                                                                   ($attachment['image_data']['url'] ?? '')));
                                                    @endphp
                                                    @if (!empty($imageUrl))
                                                        <a href="{{ $imageUrl }}" class="glightbox" 
                                                           data-gallery="conversation-{{ $msg['conversation_id'] ?? $msg['message_id'] }}">
                                                            <img src="{{ $imageUrl }}" class="message-image" loading="lazy" />
                                                        </a>
                                                    @endif
                                                @elseif ((isset($attachment['type']) && $attachment['type'] === 'video') || 
                                                         (isset($attachment['mime_type']) && strpos($attachment['mime_type'], 'video') === 0))
                                                    @if (!empty($fileUrl) && filter_var($fileUrl, FILTER_VALIDATE_URL))
                                                        <video controls class="message-video" style="max-width: 300px; border-radius: 8px;" preload="metadata">
                                                            <source src="{{ $fileUrl }}" type="video/mp4">
                                                            <source src="{{ $fileUrl }}" type="video/webm">
                                                            Trình duyệt không hỗ trợ video. <a href="{{ $fileUrl }}" target="_blank">Tải về</a>
                                                        </video>
                                                    @else
                                                        <div style="color: red; font-style: italic;">
                                                            Video không thể phát hoặc đã hết hạn. <a href="{{ $fileUrl }}" target="_blank">Tải video</a>
                                                        </div>
                                                    @endif
                                                @elseif ((isset($attachment['type']) && $attachment['type'] === 'audio') || 
                                                         (isset($attachment['mime_type']) && strpos($attachment['mime_type'], 'audio') === 0))
                                                    <div class="message-audio">
                                                        <audio controls>
                                                            <source src="{{ $fileUrl }}" type="audio/mpeg">
                                                            Your browser does not support the audio element.
                                                        </audio>
                                                    </div>
                                                @elseif ($isDocument)
                                                    <div class="message-document">
                                                        <a href="{{ $fileUrl }}" target="_blank" class="flex items-center gap-2">
                                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                            <span style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                                                {{ $attachment['name'] ?? 'Tài liệu' }}
                                                            </span>
                                                        </a>
                                                    </div>

                                                    
                                                @elseif (!empty($attachment['generic_template']))
                                                    @php
                                                        $template = $attachment['generic_template'];
                                                        $title = $template['title'] ?? '';
                                                        $mediaUrl = $template['media_url'] ?? '';
                                                        $cta = $template['cta'] ?? [];
                                                    @endphp
                                                    <div class="generic-template"
                                                        style="margin-top: 8px; background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #ccc;">
                                                        <!-- Hiển thị hình ảnh nếu có -->
                                                        @if (!empty($mediaUrl))
                                                            <a href="{{ $mediaUrl }}" class="glightbox"
                                                                data-gallery="conversation-{{ $msg['conversation_id'] ?? $msg['message_id'] }}">
                                                                <img src="{{ $mediaUrl }}"
                                                                    style="width: 100%; max-width: 300px; border-radius: 4px;"
                                                                    loading="lazy" />
                                                            </a>
                                                        @endif

                                                        <!-- Hiển thị tiêu đề nếu có -->
                                                        @if (!empty($title))
                                                            <div style="font-weight: bold; margin-top: 5px;">
                                                                {{ $title }}</div>
                                                        @endif

                                                        <!-- Hiển thị các nút CTA -->
                                                        @if (!empty($cta))
                                                            <div style="margin-top: 6px;">
                                                                @foreach ($cta as $button)
                                                                    <a href="{{ $button['url'] ?? '#' }}"
                                                                        target="_blank">
                                                                        {{ $button['title'] ?? 'Xem' }}
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @elseif (isset($attachment['type']) && $attachment['type'] === 'fallback')
                                                    @php
                                                        $title = $attachment['title'] ?? 'Đính kèm dạng fallback';
                                                        $url = $attachment['url'] ?? ($attachment['payload']['url'] ?? '');
                                                    @endphp
                                                    <div class="attachment-fallback">
                                                        <strong>[Đính kèm]</strong> {{ $title }}
                                                        @if ($url)
                                                            <br><a href="{{ $url }}" target="_blank" class="text-blue-600 underline">Mở liên kết</a>
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="attachment-fallback">
                                                        [Đính kèm không xác định]
                                                        <pre style="font-size: 10px; background: #fef9c3; padding: 4px; border-radius: 4px; overflow-x: auto;">
                                                            {{-- {{ json_encode($attachment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }} --}}
                                                        </pre>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="message-time received">
                                        {{ Carbon::parse($msg['created_time'])->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}
                                    </div>
                                </div>
                            @endif

                            @if ($isMine)
                                <div class="message sent">
                                    <div>{{ $msg['message'] }}</div>
                                    @if (isset($msg['attachments']['data']) && !empty($msg['attachments']['data']))
                                        <div class="image-gallery">
                                         
                                            @foreach ($msg['attachments']['data'] as $attachment)
                                                @php
                                                    $fileUrl = $attachment['payload']['url'] ?? ($attachment['url'] ?? ($attachment['file_url'] ?? ''));
                                                    $isDocument = isset($attachment['mime_type']) && (
                                                        strpos($attachment['mime_type'], 'pdf') !== false ||
                                                        strpos($attachment['mime_type'], 'msword') !== false ||
                                                        strpos($attachment['mime_type'], 'wordprocessingml') !== false ||
                                                        strpos($attachment['mime_type'], 'ms-excel') !== false ||
                                                        strpos($attachment['mime_type'], 'spreadsheetml') !== false ||
                                                        strpos($attachment['mime_type'], 'ms-powerpoint') !== false ||
                                                        strpos($attachment['mime_type'], 'presentationml') !== false
                                                    );
                                                @endphp
                                                @if ((isset($attachment['type']) && $attachment['type'] === 'image') || 
                                                     (isset($attachment['mime_type']) && strpos($attachment['mime_type'], 'image') === 0))
                                                    @php
                                                        $imageUrl = $attachment['payload']['url'] ?? 
                                                                   ($attachment['url'] ?? 
                                                                   ($attachment['file_url'] ?? 
                                                                   ($attachment['image_data']['url'] ?? '')));
                                                    @endphp
                                                    @if (!empty($imageUrl))
                                                        <a href="{{ $imageUrl }}" class="glightbox" 
                                                           data-gallery="conversation-{{ $msg['conversation_id'] ?? $msg['message_id'] }}">
                                                            <img src="{{ $imageUrl }}" class="message-image" loading="lazy" />
                                                        </a>
                                                    @endif
                                                @elseif ((isset($attachment['type']) && $attachment['type'] === 'video') || 
                                                         (isset($attachment['mime_type']) && strpos($attachment['mime_type'], 'video') === 0))
                                                    @if (!empty($fileUrl) && filter_var($fileUrl, FILTER_VALIDATE_URL))
                                                        <video controls class="message-video" style="max-width: 300px; border-radius: 8px;" preload="metadata">
                                                            <source src="{{ $fileUrl }}" type="video/mp4">
                                                            <source src="{{ $fileUrl }}" type="video/webm">
                                                            Trình duyệt không hỗ trợ video. <a href="{{ $fileUrl }}" target="_blank">Tải về</a>
                                                        </video>
                                                    @else
                                                        <div style="color: red; font-style: italic;">
                                                            Video không thể phát hoặc đã hết hạn. <a href="{{ $fileUrl }}" target="_blank">Tải video</a>
                                                        </div>
                                                    @endif
                                                @elseif ((isset($attachment['type']) && $attachment['type'] === 'audio') || 
                                                         (isset($attachment['mime_type']) && strpos($attachment['mime_type'], 'audio') === 0))
                                                    <div class="message-audio">
                                                        <audio controls>
                                                            <source src="{{ $fileUrl }}" type="audio/mpeg">
                                                            Your browser does not support the audio element.
                                                        </audio>
                                                    </div>
                                                @elseif ($isDocument)
                                                    <div class="message-document">
                                                        <a href="{{ $fileUrl }}" target="_blank" class="flex items-center gap-2">
                                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                            <span style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                                                {{ $attachment['name'] ?? 'Tài liệu' }}
                                                            </span>
                                                        </a>
                                                    </div>
                                                @elseif (!empty($attachment['generic_template']))
                                                    @php
                                                        $template = $attachment['generic_template'];
                                                        $title = $template['title'] ?? '';
                                                        $mediaUrl = $template['media_url'] ?? '';
                                                        $cta = $template['cta'] ?? [];
                                                    @endphp
                                                    <div class="generic-template"
                                                        style="margin-top: 8px; background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #ccc;">
                                                        <!-- Hiển thị hình ảnh nếu có -->
                                                        @if (!empty($mediaUrl))
                                                            <a href="{{ $mediaUrl }}" class="glightbox"
                                                                data-gallery="conversation-{{ $msg['conversation_id'] ?? $msg['message_id'] }}">
                                                                <img src="{{ $mediaUrl }}"
                                                                    style="width: 100%; max-width: 300px; border-radius: 4px;"
                                                                    loading="lazy" />
                                                            </a>
                                                        @endif

                                                        <!-- Hiển thị tiêu đề nếu có -->
                                                        @if (!empty($title))
                                                            <div style="font-weight: bold; margin-top: 5px;">
                                                                {{ $title }}</div>
                                                        @endif

                                                        <!-- Hiển thị các nút CTA -->
                                                        @if (!empty($cta))
                                                            <div style="margin-top: 6px;">
                                                                @foreach ($cta as $button)
                                                                    <a href="{{ $button['url'] ?? '#' }}"
                                                                        target="_blank">
                                                                        {{ $button['title'] ?? 'Xem' }}
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @elseif (isset($attachment['type']) && $attachment['type'] === 'fallback')
                                                    @php
                                                        $title = $attachment['title'] ?? 'Đính kèm dạng fallback';
                                                        $url = $attachment['url'] ?? ($attachment['payload']['url'] ?? '');
                                                    @endphp
                                                    <div class="attachment-fallback">
                                                        <strong>[Đính kèm]</strong> {{ $title }}
                                                        @if ($url)
                                                            <br><a href="{{ $url }}" target="_blank" class="text-blue-600 underline">Mở liên kết</a>
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="attachment-fallback">
                                                        [Đính kèm không xác định]
                                                        <pre style="font-size: 10px; background: #fef9c3; padding: 4px; border-radius: 4px; overflow-x: auto;">
                                                            {{-- {{ json_encode($attachment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }} --}}
                                                        </pre>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="message-time sent">
                                        {{ Carbon::parse($msg['created_time'])->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}
                                    </div>
                                </div>
                                <img src="{{ $this->currentPageAvatar }}"
                                    alt="{{ $platformAccount->name ?? 'Page' }}'s avatar" class="avatar"
                                    loading="lazy" />
                            @endif
                        </div>
                    @endforeach
                </div>

                <form wire:submit.prevent="sendReply" class="reply-form">
                    <span class="emoji-button">😊</span>
                    <div class="emoji-picker">
                        <span class="emoji-option">😀</span>
                        <span class="emoji-option">😊</span>
                        <span class="emoji-option">😂</span>
                        <span class="emoji-option">😢</span>
                        <span class="emoji-option">😍</span>
                        <span class="emoji-option">😘</span>
                        <span class="emoji-option">😜</span>
                        <span class="emoji-option">😎</span>
                        <span class="emoji-option">😡</span>
                        <span class="emoji-option">😱</span>
                        <span class="emoji-option">🥳</span>
                        <span class="emoji-option">🥰</span>
                        <span class="emoji-option">🤗</span>
                        <span class="emoji-option">🤓</span>
                        <span class="emoji-option">🤩</span>
                        <span class="emoji-option">🙈</span>
                        <span class="emoji-option">🙉</span>
                        <span class="emoji-option">🙊</span>
                        <span class="emoji-option">👍</span>
                        <span class="emoji-option">👎</span>
                        <span class="emoji-option">👊</span>
                        <span class="emoji-option">✌️</span>
                        <span class="emoji-option">🙌</span>
                        <span class="emoji-option">👏</span>
                        <span class="emoji-option">🙏</span>
                        <span class="emoji-option">💪</span>
                        <span class="emoji-option">❤️</span>
                        <span class="emoji-option">💖</span>
                        <span class="emoji-option">💔</span>
                        <span class="emoji-option">💕</span>
                        <span class="emoji-option">💯</span>
                        <span class="emoji-option">🔥</span>
                        <span class="emoji-option">🌟</span>
                        <span class="emoji-option">✨</span>
                        <span class="emoji-option">⚡️</span>
                        <span class="emoji-option">🎉</span>
                        <span class="emoji-option">🎈</span>
                        <span class="emoji-option">🎁</span>
                        <span class="emoji-option">🎂</span>
                        <span class="emoji-option">🍎</span>
                        <span class="emoji-option">🍌</span>
                        <span class="emoji-option">🍕</span>
                        <span class="emoji-option">🍔</span>
                        <span class="emoji-option">🍦</span>
                        <span class="emoji-option">☕</span>
                        <span class="emoji-option">🍷</span>
                        <span class="emoji-option">🐶</span>
                        <span class="emoji-option">🐱</span>
                        <span class="emoji-option">🐼</span>
                        <span class="emoji-option">🦁</span>
                        <span class="emoji-option">🐘</span>
                        <span class="emoji-option">🌹</span>
                        <span class="emoji-option">🌻</span>
                        <span class="emoji-option">🌈</span>
                        <span class="emoji-option">☀️</span>
                        <span class="emoji-option">🌙</span>
                        <span class="emoji-option">⭐</span>
                        <span class="emoji-option">⛄</span>
                        <span class="emoji-option">⚽</span>
                        <span class="emoji-option">🏀</span>
                        <span class="emoji-option">🎸</span>
                        <span class="emoji-option">🎤</span>
                        <span class="emoji-option">🎮</span>
                        <span class="emoji-option">🚗</span>
                        <span class="emoji-option">✈️</span>
                        <span class="emoji-option">🚀</span>
                    </div>
                    <label class="upload-button">
                        📎
                        <input type="file" wire:model="uploadFile" style="display: none;" multiple
                            accept="image/*,video/*,audio/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation" />
                    </label>
                    <span class="microphone-button">🎤</span>
                    <div class="preview-images"></div>
                    <textarea wire:model.live="replyMessage" placeholder="Nhập tin nhắn trả lời..." class="reply-input" rows="2"
                        style="resize: none;" maxlength="1000"></textarea>
                    <img src="/images/like-icon.png" alt="Like Button" class="like-button">
                    <button type="submit" class="send-button" wire:loading.class="disabled" wire:target="sendReply"
                        :disabled="!$wire.replyMessage && !$wire.uploadFile.length || !$wire.selectedConversationId">
                        <span wire:loading wire:target="sendReply">
                            <svg class="spinner h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 12a8 8 0 0116 0 8 8 0 01-16 0zm8-8a8 8 0 00-8 8h4m4 8a8 8 0 008-8h-4" />
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="sendReply">
                            <svg class="h-5 w-5" fill="none" stroke="#ffffff" viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9-7-9-7v14zm0 0H3l9-7-9 7h9z" />
                            </svg>
                        </span>
                    </button>
                </form>
            @else
                <p class="message-placeholder">Vui lòng chọn người gửi ở bên trái để xem tin nhắn.</p>
            @endif
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" />
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
        <script>
            function reloadLightbox() {
                if (window.lightbox && typeof window.lightbox.destroy === 'function') {
                    window.lightbox.destroy();
                }

                window.lightbox = GLightbox({
                    selector: '.glightbox',
                    touchNavigation: true,
                    loop: true,
                });
            }

            document.addEventListener('DOMContentLoaded', reloadLightbox);

            document.addEventListener('livewire:load', () => {
                reloadLightbox();

                Livewire.hook('message.processed', (message, component) => {
                    reloadLightbox();
                });
            });
        </script>
    @endpush
</x-filament::page>