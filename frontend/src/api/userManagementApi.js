import { apiRequest } from "./apiClient";

export function getUsers(token) {
    return apiRequest("/admin/users", {
        token,
    });
}

export function getUser(userId, token) {
    return apiRequest(`/admin/users/${userId}`, {
        token,
    });
}

export function activateUser(userId, token) {
    return apiRequest(`/admin/users/${userId}/activate`, {
        method: "PATCH",
        token,
    });
}

export function deactivateUser(userId, token) {
    return apiRequest(`/admin/users/${userId}/deactivate`, {
        method: "PATCH",
        token,
    });
}

export function updateUserRole(userId, roleName, token) {
    return apiRequest(`/admin/users/${userId}/role`, {
        method: "PATCH",
        token,
        body: {
            roleName,
        },
    });
}

export function updateUser(userId, data, token) {
    return apiRequest(`/admin/users/${userId}`, {
        method: "PATCH",
        token,
        body: data,
    });
}