import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router";

import { getDashboardSummary } from "../api/dashboardApi";
import DashboardLayout from "../components/Dashboard/DashboardLayout";

function formatDateTime(value) {
  if (!value) return "";

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function userName(user) {
  if (!user) return "System";

  return (
    user.fullName ||
    `${user.firstName || ""} ${user.lastName || ""}`.trim() ||
    user.email ||
    "System"
  );
}

function LatestUpdatesPage() {
  const [updates, setUpdates] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  const loadUpdates = useCallback(async () => {
    try {
      setIsLoading(true);
      setErrorMessage("");

      const response = await getDashboardSummary();
      setUpdates(
        Array.isArray(response?.data?.latestUpdates)
          ? response.data.latestUpdates
          : []
      );
    } catch (error) {
      setErrorMessage(
        error?.message || "Unable to load the latest ticket updates."
      );
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadUpdates();
  }, [loadUpdates]);

  return (
    <DashboardLayout>
      <div className="dashboard-heading-row">
        <div>
          <h1>Latest updates</h1>
          <p>Ticket activity recorded during the last two months.</p>
        </div>

        <button
          type="button"
          className="refresh-updates-button"
          onClick={loadUpdates}
          disabled={isLoading}
        >
          {isLoading ? "Refreshing..." : "Refresh"}
        </button>
      </div>

      {errorMessage && (
        <div className="dashboard-alert">
          <span>{errorMessage}</span>
          <button type="button" onClick={loadUpdates}>Retry</button>
        </div>
      )}

      <section className="dashboard-panel updates-page-panel">
        <div className="panel-heading">
          <div>
            <h2>Activity history</h2>
            <span>{updates.length} recent updates</span>
          </div>
        </div>

        <div className="latest-updates-list">
          {updates.map((update) => {
            const ticketId = update.ticket?.id || update.ticketId;
            const ticketNumber =
              update.ticket?.ticketNumber || "Deleted ticket";

            return (
              <article className="latest-update" key={update.id}>
                <span className="update-dot" aria-hidden="true" />

                <div>
                  <strong>{update.description || update.action}</strong>
                  <p>
                    {ticketId ? (
                      <Link to={`/tickets/${ticketId}`}>
                        {ticketNumber}
                      </Link>
                    ) : (
                      ticketNumber
                    )}
                    {` · ${userName(update.user)}`}
                  </p>
                </div>

                <time dateTime={update.createdAt}>
                  {formatDateTime(update.createdAt)}
                </time>
              </article>
            );
          })}

          {!isLoading && updates.length === 0 && !errorMessage && (
            <p className="empty-panel">No recent ticket activity.</p>
          )}

          {isLoading && updates.length === 0 && (
            <p className="empty-panel">Loading updates...</p>
          )}
        </div>
      </section>
    </DashboardLayout>
  );
}

export default LatestUpdatesPage;