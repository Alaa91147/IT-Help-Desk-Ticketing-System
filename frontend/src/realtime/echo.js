import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

export function createEcho(token) {
  if (!token) return null;

  return new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:
      import.meta.env.VITE_REVERB_HOST ||
      window.location.hostname,
    wsPort: Number(
      import.meta.env.VITE_REVERB_PORT || 8080
    ),
    wssPort: Number(
      import.meta.env.VITE_REVERB_PORT || 8080
    ),
    forceTLS:
      import.meta.env.VITE_REVERB_SCHEME === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: "/api/broadcasting/auth",
    auth: {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
    },
  });
}