import {
  apiDownload,
  apiRequest,
} from "./apiClient";

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
    { token }
  );
}

export function getTicketById(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}`, { token });
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
  { assignedUserId, reason = "" },
  token
) {
  return apiRequest(`/tickets/${ticketId}/assign`, {
    method: "PATCH",
    token,
    body: {
      assignedUserId: Number(assignedUserId),
      ...(reason.trim() ? { reason: reason.trim() } : {}),
    },
  });
}

export function startTicket(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}/start`, {
    method: "PATCH",
    token,
  });
}

export function pauseTicket(ticketId, reason, token) {
  return apiRequest(`/tickets/${ticketId}/pause`, {
    method: "PATCH",
    token,
    body: reason?.trim()
      ? { reason: reason.trim() }
      : {},
  });
}

export function resumeTicket(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}/resume`, {
    method: "PATCH",
    token,
  });
}

export function resolveTicket(
  ticketId,
  resolutionNote,
  token
) {
  return apiRequest(`/tickets/${ticketId}/resolve`, {
    method: "PATCH",
    token,
    body: {
      resolutionNote: resolutionNote.trim(),
    },
  });
}

export function escalateTicket(ticketId, reason, token) {
  return apiRequest(`/tickets/${ticketId}/escalate`, {
    method: "PATCH",
    token,
    body: {
      reason: reason.trim(),
    },
  });
}

export function cancelTicket(ticketId, reason, token) {
  return apiRequest(`/tickets/${ticketId}/cancel`, {
    method: "PATCH",
    token,
    body: {
      reason: reason.trim(),
    },
  });
}

export function closeTicket(
  ticketId,
  closingNote,
  token
) {
  return apiRequest(`/tickets/${ticketId}/close`, {
    method: "PATCH",
    token,
    body: closingNote?.trim()
      ? { closingNote: closingNote.trim() }
      : {},
  });
}

export function getTicketComments(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}/comments`, {
    token,
  });
}

export function addTicketComment(
  ticketId,
  { comment, isInternal = false, parentCommentId = null },
  token
) {
  return apiRequest(`/tickets/${ticketId}/comments`, {
    method: "POST",
    token,
    body: {
      comment: comment.trim(),
      isInternal: Boolean(isInternal),
      ...(parentCommentId
        ? { parentCommentId: Number(parentCommentId) }
        : {}),
    },
  });
}

export function updateTicketComment(
  ticketId,
  commentId,
  { comment, isInternal },
  token
) {
  return apiRequest(
    `/tickets/${ticketId}/comments/${commentId}`,
    {
      method: "PATCH",
      token,
      body: {
        comment: comment.trim(),
        ...(typeof isInternal === "boolean"
          ? { isInternal }
          : {}),
      },
    }
  );
}

export function deleteTicketComment(
  ticketId,
  commentId,
  token
) {
  return apiRequest(
    `/tickets/${ticketId}/comments/${commentId}`,
    {
      method: "DELETE",
      token,
    }
  );
}

export function getTicketAttachments(ticketId, token) {
  return apiRequest(`/tickets/${ticketId}/attachments`, {
    token,
  });
}

export function uploadTicketAttachment(
  ticketId,
  file,
  token
) {
  const formData = new FormData();
  formData.append("file", file);

  return apiRequest(`/tickets/${ticketId}/attachments`, {
    method: "POST",
    token,
    body: formData,
  });
}

export function downloadTicketAttachment(
  ticketId,
  attachment,
  token
) {
  return apiDownload(
    `/tickets/${ticketId}/attachments/${attachment.id}/download`,
    {
      token,
      fallbackFileName: attachment.fileName || "attachment",
    }
  );
}

export function deleteTicketAttachment(
  ticketId,
  attachmentId,
  token
) {
  return apiRequest(
    `/tickets/${ticketId}/attachments/${attachmentId}`,
    {
      method: "DELETE",
      token,
    }
  );
}