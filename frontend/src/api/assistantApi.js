import { apiRequest } from './apiClient';

export function assistantChat(messages, token) {
  return apiRequest('/assistant/chat', {
    method: 'POST',
    token,
    body: { messages },
  });
}
