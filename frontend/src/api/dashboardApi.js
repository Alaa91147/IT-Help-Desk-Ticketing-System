import { apiRequest } from "./apiClient";
import { getAuthToken } from "../utils/authStorage";

function queryString(filters = {}) {
  const query = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      query.append(key, String(value));
    }
  });

  return query.toString();
}

export function getDashboardSummary(filters = {}) {
  const query = queryString(filters);

  return apiRequest(
    query
      ? `/manager/reports/tickets?${query}`
      : "/manager/reports/tickets",
    {
      token: getAuthToken(),
    }
  );
}

export function getRecentTickets() {
  return apiRequest(
    "/tickets?perPage=5&sortBy=createdAt&sortDirection=desc",
    {
      token: getAuthToken(),
    }
  );
}