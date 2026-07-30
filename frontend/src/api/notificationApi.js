import { apiRequest } from "./apiClient";

export function getNotifications(
  token,
  filters = {}
) {
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

  const queryString = query.toString();

  return apiRequest(
    queryString
      ? `/notifications?${queryString}`
      : "/notifications",
    { token }
  );
}

export function markNotificationAsRead(
  notificationId,
  token
) {
  return apiRequest(
    `/notifications/${notificationId}/read`,
    {
      method: "PATCH",
      token,
    }
  );
}

export function markAllNotificationsAsRead(token) {
  return apiRequest("/notifications/read-all", {
    method: "PATCH",
    token,
  });
}

export function deleteNotification(
  notificationId,
  token
) {
  return apiRequest(
    `/notifications/${notificationId}`,
    {
      method: "DELETE",
      token,
    }
  );
}