import { apiRequest } from "./apiClient";
import { getAuthToken } from "../utils/authStorage";

export function getDashboardSummary() {
  return apiRequest("/manager/reports/tickets", {
    token: getAuthToken(),
  });
}

export function getRecentTickets() {
  return apiRequest("/tickets", {
    token: getAuthToken(),
  });
}