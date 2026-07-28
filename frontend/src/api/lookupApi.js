import { apiRequest } from "./apiClient";

export function getCategories(token) {
  return apiRequest("/categories", {
    token,
  });
}

export function getPriorities(token) {
  return apiRequest("/priorities", {
    token,
  });
}

export function getStatuses(token) {
  return apiRequest("/statuses", {
    token,
  });
}