const API_BASE_URL = "/api";

export async function apiRequest(
  endpoint,
  {
    method = "GET",
    body = null,
    token = null,
    headers = {},
  } = {}
) {
  const requestHeaders = {
    Accept: "application/json",
    ...headers,
  };

  if (body !== null) {
    requestHeaders["Content-Type"] = "application/json";
  }

  if (token) {
    requestHeaders.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    method,
    headers: requestHeaders,
    body: body !== null ? JSON.stringify(body) : null,
  });

  let responseData = null;

  try {
    responseData = await response.json();
  } catch {
    responseData = null;
  }

  if (!response.ok) {
    const error = new Error(
      responseData?.message || "Something went wrong. Please try again."
    );

    error.status = response.status;
    error.data = responseData;

    throw error;
  }

  return responseData;
}