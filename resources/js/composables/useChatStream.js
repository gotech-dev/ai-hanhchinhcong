export function useChatStream() {
    const streamResponse = async (sessionId, message, onChunk, onComplete, onError, attachments = [], onReport = null, onDocument = null, templateId = null) => {
        // ✅ FIX: Log all parameters to debug
        console.log('[useChatStream] streamResponse called - ALL PARAMS:', {
            sessionId,
            hasMessage: !!message,
            hasOnChunk: !!onChunk,
            hasOnComplete: !!onComplete,
            hasOnError: !!onError,
            attachmentsCount: attachments?.length || 0,
            attachmentsIsArray: Array.isArray(attachments),
            hasOnReport: !!onReport,
            onReportType: typeof onReport,
            onReportValue: onReport,
            hasOnDocument: !!onDocument,
            onDocumentType: typeof onDocument,
            onDocumentIsFunction: typeof onDocument === 'function',
            onDocumentIsNull: onDocument === null,
            onDocumentIsUndefined: onDocument === undefined,
            onDocumentValue: onDocument,
        });

        try {
            console.log('[useChatStream] 🚀 Starting fetch request', {
                url: `/api/chat/sessions/${sessionId}/stream`,
                sessionId,
                hasMessage: !!message,
                messageLength: message?.length || 0,
                attachmentsCount: attachments?.length || 0,
                timestamp: Date.now(),
            });

            const response = await fetch(`/api/chat/sessions/${sessionId}/stream`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    message: message || null,
                    attachments: attachments.length > 0 ? attachments : null,
                    template_id: templateId || null,
                }),
            });

            console.log('[useChatStream] 📡 Fetch response received', {
                status: response.status,
                statusText: response.statusText,
                ok: response.ok,
                hasBody: !!response.body,
                contentType: response.headers.get('content-type'),
                timestamp: Date.now(),
            });

            if (!response.ok) {
                console.error('[useChatStream] ❌ Fetch failed', {
                    status: response.status,
                    statusText: response.statusText,
                    timestamp: Date.now(),
                });
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            if (!response.body) {
                console.error('[useChatStream] ❌ No response body', {
                    timestamp: Date.now(),
                });
                throw new Error('No response body');
            }

            console.log('[useChatStream] 🚀 Starting SSE stream reader', {
                hasBody: !!response.body,
                timestamp: Date.now(),
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let doneData = null; // Store done event data
            let totalBytesReceived = 0;
            let totalChunksReceived = 0;
            let firstChunkTime = null;
            let lineCount = 0;

            while (true) {
                const { value, done } = await reader.read();

                if (done) {
                    console.log('[useChatStream] ✅ Stream reader done', {
                        totalBytesReceived,
                        totalChunksReceived,
                        bufferLength: buffer.length,
                        lineCount,
                        timestamp: Date.now(),
                    });
                    break;
                }

                if (value) {
                    totalBytesReceived += value.length;
                    const decoded = decoder.decode(value, { stream: true });
                    buffer += decoded;

                    console.log('[useChatStream] 📦 Raw data received', {
                        bytesReceived: value.length,
                        totalBytesReceived,
                        decodedLength: decoded.length,
                        bufferLength: buffer.length,
                        timestamp: Date.now(),
                    });
                }

                const lines = buffer.split('\n');
                lineCount += lines.length;

                // Keep the last incomplete line in buffer
                buffer = lines.pop() || '';

                console.log('[useChatStream] 📝 Processing lines', {
                    linesCount: lines.length,
                    bufferLength: buffer.length,
                    totalLinesProcessed: lineCount,
                    timestamp: Date.now(),
                });

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        try {
                            const jsonStr = line.slice(6).trim();
                            if (!jsonStr) {
                                console.log('[useChatStream] ⚠️ Empty data line, skipping');
                                continue; // Skip empty lines
                            }

                            console.log('[useChatStream] 📨 Parsing SSE data', {
                                jsonStrLength: jsonStr.length,
                                jsonStrPreview: jsonStr.substring(0, 100),
                                timestamp: Date.now(),
                            });

                            const data = JSON.parse(jsonStr);

                            console.log('[useChatStream] ✅ Parsed SSE data', {
                                type: data.type,
                                hasContent: !!data.content,
                                contentLength: data.content?.length || 0,
                                hasStatus: !!data.status,
                                timestamp: Date.now(),
                            });

                            if (data.type === 'content' && data.content) {
                                totalChunksReceived++;
                                if (!firstChunkTime) {
                                    firstChunkTime = Date.now();
                                }

                                // ✅ FIX: Gửi chunk NGAY LẬP TỨC, không batch, không delay
                                // Để đảm bảo streaming realtime
                                console.log('[useChatStream] ✅ Content chunk received', {
                                    chunkNumber: totalChunksReceived,
                                    chunkSize: data.content.length,
                                    chunkPreview: data.content.substring(0, 50),
                                    timeSinceStart: firstChunkTime ? Date.now() - firstChunkTime : 0,
                                    timestamp: Date.now(),
                                });

                                onChunk(data.content);

                                console.log('[useChatStream] ✅ onChunk called', {
                                    chunkNumber: totalChunksReceived,
                                    timestamp: Date.now(),
                                });
                            } else if (data.type === 'status') {
                                console.log('[useChatStream] 📊 Status message received', {
                                    status: data.status,
                                    message: data.message,
                                    timestamp: Date.now(),
                                });

                                // ✅ FIX STREAMING: Xử lý status message (loading/ready)
                                if (data.status === 'processing' && data.message) {
                                    console.log('[useChatStream] 📊 Sending loading message', {
                                        message: data.message,
                                        timestamp: Date.now(),
                                    });
                                    // Hiển thị loading message tạm thời - gửi như content với flag đặc biệt
                                    // Sử dụng một prefix đặc biệt để frontend biết đây là loading message
                                    onChunk('__LOADING__' + data.message + '\n\n');
                                } else if (data.status === 'ready') {
                                    console.log('[useChatStream] 📊 Sending clear loading signal');
                                    // ✅ FIX: Gửi signal để clear loading message
                                    // Frontend sẽ clear content khi nhận được signal này
                                    onChunk('__CLEAR_LOADING__');
                                }
                            } else if (data.type === 'done') {
                                console.log('[useChatStream] ✅ Done event received', {
                                    messageId: data.message_id,
                                    hasDocument: !!data.document,
                                    hasReport: !!data.report,
                                    timestamp: Date.now(),
                                });
                                // Store done event data
                                doneData = data;

                                console.log('[useChatStream] Done event received', {
                                    hasReport: !!data.report,
                                    hasDocument: !!data.document,
                                    messageId: data.message_id,
                                    document: data.document,
                                    hasOnDocument: !!onDocument,
                                    onDocumentType: typeof onDocument,
                                });

                                // Handle report data if exists (immediate callback)
                                if (data.report && onReport) {
                                    console.log('[useChatStream] Calling onReport callback');
                                    try {
                                        onReport(data.report, data.message_id);
                                    } catch (e) {
                                        console.error('[useChatStream] Error in onReport:', e);
                                    }
                                }

                                // Handle document data if exists (new callback)
                                console.log('[useChatStream] Checking document callback', {
                                    hasDocument: !!data.document,
                                    hasOnDocument: !!onDocument,
                                    document: data.document,
                                });

                                if (data.document && onDocument) {
                                    console.log('[useChatStream] Calling onDocument callback', {
                                        document: data.document,
                                        messageId: data.message_id,
                                    });
                                    try {
                                        onDocument(data.document, data.message_id);
                                    } catch (e) {
                                        console.error('[useChatStream] Error in onDocument:', e);
                                    }
                                } else {
                                    console.warn('[useChatStream] Document callback not called', {
                                        hasDocument: !!data.document,
                                        hasOnDocument: !!onDocument,
                                    });
                                }
                            } else if (data.type === 'error') {
                                if (onError) onError(data.content || 'An error occurred');
                            }
                        } catch (e) {
                            console.error('Error parsing SSE data:', e, {
                                line: line.substring(0, 100),
                            });
                        }
                    }
                }
            }

            // ✅ FIX: Không cần flush pending chunks nữa vì đã flush ngay từng chunk

            // Process any remaining buffer
            if (buffer.trim()) {
                const lines = buffer.split('\n');
                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        try {
                            const jsonStr = line.slice(6).trim();
                            if (!jsonStr) continue;

                            const data = JSON.parse(jsonStr);
                            if (data.type === 'done') {
                                doneData = data;

                                if (data.report && onReport) {
                                    try {
                                        onReport(data.report, data.message_id);
                                    } catch (e) {
                                        console.error('[useChatStream] Error in onReport:', e);
                                    }
                                }

                                if (data.document && onDocument) {
                                    console.log('[useChatStream] Calling onDocument from buffer', {
                                        document: data.document,
                                        messageId: data.message_id,
                                    });
                                    try {
                                        onDocument(data.document, data.message_id);
                                    } catch (e) {
                                        console.error('[useChatStream] Error in onDocument:', e);
                                    }
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing final SSE data:', e);
                        }
                    }
                }
            }

            // Call onComplete at the very end with all done data
            if (onComplete) {
                console.log('[useChatStream] Stream complete, calling onComplete', {
                    hasDoneData: !!doneData,
                    hasDocument: !!doneData?.document,
                });
                try {
                    await onComplete(doneData || {});
                } catch (e) {
                    console.error('[useChatStream] Error in onComplete:', e);
                }
            }

        } catch (error) {
            console.error('Stream error:', error);
            if (onError) onError(error.message || 'Failed to stream response');
        }
    };

    return { streamResponse };
}

