import { apiRequest } from "./apiClient";

export function submitAgentRequest(ticketId, message, token) {
  return apiRequest(`/tickets/${ticketId}/agent-requests`, {
    method: "POST",
    token,
    body: { message: message?.trim() || null },
  });
}

export function getPendingAgentRequests(token) {
  return apiRequest("/tickets/agent-requests", { token });
}

export function acceptAgentRequest(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}/agent-request/accept`, {
    method: "PATCH",
    token,
  });
}

export function rejectAgentRequest(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}/agent-request/reject`, {
    method: "PATCH",
    token,
  });
}
