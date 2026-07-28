import { apiRequest } from "./apiClient";

function buildQueryString(filters = {}) {
  const query = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (
      value !== undefined &&
      value !== null &&
      value !== ""
    ) {
      query.append(key, String(value));
    }
  });

  return query.toString();
}

export function getTickets(token, filters = {}) {
  const queryString = buildQueryString(filters);

  return apiRequest(
    queryString ? `/tickets?${queryString}` : "/tickets",
    {
      token,
    }
  );
}

export function getTicketById(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}`, {
    token,
  });
}

export function createTicket(ticketData, token) {
  return apiRequest("/tickets", {
    method: "POST",
    token,
    body: {
      categoryId: Number(ticketData.categoryId),
      priorityId: Number(ticketData.priorityId),
      subject: ticketData.subject.trim(),
      description: ticketData.description.trim(),
    },
  });
}

export function updateTicket(ticketId, ticketData, token) {
  return apiRequest(`/tickets/${ticketId}`, {
    method: "PUT",
    token,
    body: {
      categoryId: Number(ticketData.categoryId),
      priorityId: Number(ticketData.priorityId),
      subject: ticketData.subject.trim(),
      description: ticketData.description.trim(),
    },
  });
}

export function deleteTicket(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}`, {
    method: "DELETE",
    token,
  });
}

export function assignTicket(
  ticketId,
  assignedUserId,
  token
) {
  return apiRequest(`/tickets/${ticketId}/assign`, {
    method: "PATCH",
    token,
    body: {
      assignedUserId: Number(assignedUserId),
    },
  });
}

export function startTicket(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}/start`, {
    method: "PATCH",
    token,
  });
}

export function resolveTicket(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}/resolve`, {
    method: "PATCH",
    token,
  });
}

export function closeTicket(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}/close`, {
    method: "PATCH",
    token,
  });
}