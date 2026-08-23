const API_BASE_URL =
  "https://it-help-desk-ticketing-system-1.onrender.com/api";

function buildError(responseData, fallbackMessage) {
  const error = new Error(
    responseData?.message ||
      fallbackMessage ||
      "Something went wrong. Please try again."
  );

  error.data = responseData;

  return error;
}

export async function apiRequest(
  endpoint,
  {
    method = "GET",
    body = null,
    token = null,
    headers = {},
  } = {}
) {
  const isFormData =
    typeof FormData !== "undefined" && body instanceof FormData;

  const requestHeaders = {
    Accept: "application/json",
    ...headers,
  };

  if (body !== null && !isFormData) {
    requestHeaders["Content-Type"] = "application/json";
  }

  if (token) {
    requestHeaders.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    method,
    headers: requestHeaders,
    body:
      body === null
        ? null
        : isFormData
          ? body
          : JSON.stringify(body),
  });

  let responseData = null;

  try {
    responseData = await response.json();
  } catch {
    responseData = null;
  }

  if (!response.ok) {
    const error = buildError(responseData);
    error.status = response.status;
    throw error;
  }

  return responseData;
}

export async function apiDownload(
  endpoint,
  {
    token = null,
    fallbackFileName = "download",
  } = {}
) {
  const headers = {
    Accept: "*/*",
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    method: "GET",
    headers,
  });

  if (!response.ok) {
    let responseData = null;

    try {
      responseData = await response.json();
    } catch {
      responseData = null;
    }

    const error = buildError(
      responseData,
      "Unable to download the attachment."
    );

    error.status = response.status;
    throw error;
  }

  const disposition = response.headers.get(
    "content-disposition"
  );

  const encodedName = disposition?.match(
    /filename\*=UTF-8''([^;]+)/
  )?.[1];

  const plainName = disposition?.match(
    /filename="?([^";]+)"?/
  )?.[1];

  const fileName = encodedName
    ? decodeURIComponent(encodedName)
    : plainName || fallbackFileName;

  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement("a");

  link.href = url;
  link.download = fileName;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}