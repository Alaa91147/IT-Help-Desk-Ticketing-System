import {
  useCallback,
  useEffect,
  useRef,
  useState,
} from "react";
import { useNavigate } from "react-router";

import {
  deleteNotification,
  getNotifications,
  markAllNotificationsAsRead,
  markNotificationAsRead,
} from "../../api/notificationApi";
import { useAuth } from "../../context/AuthContext";
import "../../styles/notifications.css";
import { createEcho } from "../../realtime/echo";

function notificationArray(response) {
  if (Array.isArray(response?.data?.data)) {
    return response.data.data;
  }
  if (Array.isArray(response?.data)) return response.data;
  return [];
}

function NotificationBell() {
  const navigate = useNavigate();
const { token, user } = useAuth();
  const wrapperRef = useRef(null);

  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");

  const loadNotifications = useCallback(async () => {
    if (!token) return;

    try {
      setIsLoading(true);
      setErrorMessage("");

      const response = await getNotifications(token, {
        perPage: 12,
      });

      setNotifications(notificationArray(response));
      setUnreadCount(Number(response?.unreadCount || 0));
    } catch (error) {
      setErrorMessage(
        error?.message || "Unable to load notifications."
      );
    } finally {
      setIsLoading(false);
    }
  }, [token]);

  useEffect(() => {
  if (!token || !user?.id) return undefined;

  const echo = createEcho(token);
  const channelName = `App.Models.User.${user.id}`;

  const channel = echo.private(channelName);

  channel.listen(
    ".notification.created",
    (event) => {
      const incomingNotification =
        event?.notification;

      if (!incomingNotification) return;

      setNotifications((current) => [
        incomingNotification,
        ...current.filter(
          (item) =>
            item.id !== incomingNotification.id
        ),
      ].slice(0, 12));

      if (!incomingNotification.isRead) {
        setUnreadCount((current) => current + 1);
      }
    }
  );

  return () => {
    echo.leave(channelName);
    echo.disconnect();
  };
}, [token, user?.id]);
  useEffect(() => {
    loadNotifications();

    const timer = window.setInterval(
      loadNotifications,
      60000
    );

    return () => window.clearInterval(timer);
  }, [loadNotifications]);

  useEffect(() => {
    function closeOnOutsideClick(event) {
      if (
        wrapperRef.current &&
        !wrapperRef.current.contains(event.target)
      ) {
        setIsOpen(false);
      }
    }

    document.addEventListener("mousedown", closeOnOutsideClick);
    return () =>
      document.removeEventListener(
        "mousedown",
        closeOnOutsideClick
      );
  }, []);

  async function openNotification(notification) {
    try {
      if (!notification.isRead) {
        await markNotificationAsRead(
          notification.id,
          token
        );
      }

      setNotifications((current) =>
        current.map((item) =>
          item.id === notification.id
            ? {
                ...item,
                isRead: true,
                readAt: new Date().toISOString(),
              }
            : item
        )
      );

      if (!notification.isRead) {
        setUnreadCount((current) =>
          Math.max(0, current - 1)
        );
      }

      const ticketId =
        notification.ticketId ||
        notification.ticket?.id;

      setIsOpen(false);

      if (ticketId) {
        navigate(`/tickets/${ticketId}`);
      }
    } catch (error) {
      setErrorMessage(
        error?.message || "Unable to open notification."
      );
    }
  }

  async function markAllRead() {
    try {
      await markAllNotificationsAsRead(token);
      setUnreadCount(0);
      setNotifications((current) =>
        current.map((item) => ({
          ...item,
          isRead: true,
          readAt: item.readAt || new Date().toISOString(),
        }))
      );
    } catch (error) {
      setErrorMessage(
        error?.message || "Unable to update notifications."
      );
    }
  }

  async function removeNotification(event, notificationId) {
    event.stopPropagation();

    try {
      const removed = notifications.find(
        (item) => item.id === notificationId
      );

      await deleteNotification(notificationId, token);

      setNotifications((current) =>
        current.filter((item) => item.id !== notificationId)
      );

      if (removed && !removed.isRead) {
        setUnreadCount((current) =>
          Math.max(0, current - 1)
        );
      }
    } catch (error) {
      setErrorMessage(
        error?.message || "Unable to delete notification."
      );
    }
  }

  return (
    <div className="notification-wrapper" ref={wrapperRef}>
      <button
        type="button"
        className="notification-trigger"
        aria-label="Notifications"
        onClick={() => {
          setIsOpen((current) => !current);
          if (!isOpen) loadNotifications();
        }}
      >
<span aria-hidden="true">{"\u{1F514}"}</span>
        {unreadCount > 0 && (
          <span className="notification-count">
            {unreadCount > 99 ? "99+" : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <div className="notification-panel">
          <div className="notification-heading">
            <div>
              <strong>Notifications</strong>
              <span>{unreadCount} unread</span>
            </div>
            {unreadCount > 0 && (
              <button type="button" onClick={markAllRead}>
                Mark all read
              </button>
            )}
          </div>

          {errorMessage && (
            <div className="notification-error">
              {errorMessage}
            </div>
          )}

          <div className="notification-list">
            {isLoading && notifications.length === 0 ? (
              <p className="notification-empty">Loading…</p>
            ) : notifications.length === 0 ? (
              <p className="notification-empty">
                You have no notifications.
              </p>
            ) : (
              notifications.map((notification) => (
                <button
                  type="button"
                  className={`notification-item ${
                    notification.isRead ? "" : "is-unread"
                  }`}
                  key={notification.id}
                  onClick={() =>
                    openNotification(notification)
                  }
                >
                  <span className="notification-dot" />
                  <span className="notification-copy">
                    <strong>{notification.title}</strong>
                    <span>{notification.message}</span>
                    <time>
                      {new Date(
                        notification.createdAt
                      ).toLocaleString("en-GB")}
                    </time>
                  </span>
                  <span
                    role="button"
                    tabIndex="0"
                    className="notification-delete"
                    aria-label="Delete notification"
                    onClick={(event) =>
                      removeNotification(
                        event,
                        notification.id
                      )
                    }
                    onKeyDown={(event) => {
                      if (
                        event.key === "Enter" ||
                        event.key === " "
                      ) {
                        removeNotification(
                          event,
                          notification.id
                        );
                      }
                    }}
                  >
                    ×
                  </span>
                </button>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default NotificationBell;