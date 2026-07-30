import { apiRequest } from "./apiClient";

export function loginUser(credentials) {
  return apiRequest("/auth/login", {
    method: "POST",
    body: {
      email: credentials.email,
      password: credentials.password,
      deviceName: "react-frontend",
    },
  });
}

export function registerUser(userData) {
  return apiRequest("/auth/register", {
    method: "POST",
    body: {
      firstName: userData.firstName,
      lastName: userData.lastName,
      email: userData.email,
      phoneNumber: userData.phoneNumber || null,
      password: userData.password,
      password_confirmation: userData.confirmPassword,
    },
  });
}

export function verifyOtp(email, otp) {
  return apiRequest("/auth/verify-otp", {
    method: "POST",
    body: {
      email,
      otp,
    },
  });
}

export function resendOtp(email) {
  return apiRequest("/auth/resend-otp", {
    method: "POST",
    body: {
      email,
    },
  });
}

export function forgotPassword(email) {
  return apiRequest("/auth/forgot-password", {
    method: "POST",
    body: {
      email,
    },
  });
}

export function resetPassword(resetData) {
  return apiRequest("/auth/reset-password", {
    method: "POST",
    body: {
      email: resetData.email,
      token: resetData.token,
      password: resetData.password,
      password_confirmation: resetData.confirmPassword,
    },
  });
}

export function getCurrentUser(token) {
  return apiRequest("/auth/me", {
    token,
  });
}

export function updateProfile(profileData, token) {
    return apiRequest("/auth/me", {
        method: "PATCH",
        token,
        body: {
            firstName: profileData.firstName,
            lastName: profileData.lastName,
            email: profileData.email,
            phoneNumber: profileData.phoneNumber,

            currentPassword: profileData.currentPassword,
            newPassword: profileData.newPassword,
            confirmPassword: profileData.confirmPassword,
        },
    });
}

export function logoutUser(token) {
  return apiRequest("/auth/logout", {
    method: "POST",
    token,
  });
}

export function verifyEmailChange(otp, token) {
    return apiRequest("/auth/me/verify-email-change", {
        method: "POST",
        token,
        body: {
            otp,
        },
    });
}

export function sendEmailChange(profileData, token) {
    return apiRequest("/auth/me", {
        method: "PATCH",
        token,
        body: profileData,
    });
}