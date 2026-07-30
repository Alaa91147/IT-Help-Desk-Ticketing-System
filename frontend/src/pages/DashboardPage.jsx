import { useCallback, useEffect, useState } from "react";

import {
  getDashboardSummary,
  getRecentTickets,
} from "../api/dashboardApi";
import DashboardLayout from "../components/Dashboard/DashboardLayout";
import RecentTicketsTable from "../components/Dashboard/RecentTicketsTable";
import StatCard from "../components/Dashboard/StatCard";

function formatDuration(seconds) {
  const value = Math.max(0, Number(seconds) || 0);
  const hours = Math.floor(value / 3600);
  const minutes = Math.floor((value % 3600) / 60);

  if (hours > 0) return `${hours}h ${minutes}m`;
  return `${minutes}m`;
}

function agentName(agent) {
  return (
    agent.fullName ||
    `${agent.firstName || ""} ${agent.lastName || ""}`.trim() ||
    agent.email
  );
}

function DashboardPage() {
  const [summary, setSummary] = useState(null);
  const [recentTickets, setRecentTickets] = useState([]);
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [appliedPeriod, setAppliedPeriod] = useState({
    dateFrom: "",
    dateTo: "",
  });
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  const loadDashboard = useCallback(async () => {
    try {
      setIsLoading(true);
      setErrorMessage("");

      const [summaryResponse, ticketsResponse] =
        await Promise.all([
          getDashboardSummary(appliedPeriod),
          getRecentTickets(),
        ]);

      setSummary(summaryResponse?.data || null);

      const paginator = ticketsResponse?.data || {};
      setRecentTickets(
        Array.isArray(paginator.data)
          ? paginator.data.slice(0, 5)
          : []
      );
    } catch (error) {
      setErrorMessage(
        error?.message || "Unable to load dashboard information."
      );
    } finally {
      setIsLoading(false);
    }
  }, [appliedPeriod]);

  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  const totals = summary?.totals || {};
  const statusRows = summary?.byStatus || [];
  const agents = summary?.agentPerformance || [];
  const largestStatusTotal = Math.max(
    1,
    ...statusRows.map((item) => Number(item.total) || 0)
  );

  function applyPeriod(event) {
    event.preventDefault();

    if (dateFrom && dateTo && dateTo < dateFrom) {
      setErrorMessage(
        "The end date must be on or after the start date."
      );
      return;
    }

    setAppliedPeriod({ dateFrom, dateTo });
  }

  return (
    <DashboardLayout>
      <div className="dashboard-heading-row">
        <div>
          <h1>Ticket operations</h1>
          <p>Workflow, workload and service-time overview.</p>
        </div>

        <form className="report-period" onSubmit={applyPeriod}>
          <label>
            From
            <input
              type="date"
              value={dateFrom}
              max={dateTo || undefined}
              onChange={(event) => setDateFrom(event.target.value)}
            />
          </label>
          <label>
            To
            <input
              type="date"
              value={dateTo}
              min={dateFrom || undefined}
              onChange={(event) => setDateTo(event.target.value)}
            />
          </label>
          <button>Apply</button>
          <button
            type="button"
            className="clear-period"
            onClick={() => {
              setDateFrom("");
              setDateTo("");
              setAppliedPeriod({
                dateFrom: "",
                dateTo: "",
              });
            }}
          >
            Clear
          </button>
        </form>
      </div>

      {errorMessage && (
        <div className="dashboard-alert">
          <span>{errorMessage}</span>
          <button onClick={loadDashboard}>Retry</button>
        </div>
      )}

      <div className="stats-grid">
        <StatCard
          title="Total tickets"
          value={totals.tickets ?? 0}
        />
        <StatCard
          title="Unassigned"
          value={totals.unassigned ?? 0}
          tone="warning"
        />
        <StatCard
          title="Overdue"
          value={totals.overdue ?? 0}
          tone="danger"
        />
        <StatCard
          title="Escalated"
          value={totals.escalated ?? 0}
          tone="danger"
        />
        <StatCard
          title="Cancelled"
          value={totals.cancelled ?? 0}
        />
        <StatCard
          title="Reassignments"
          value={totals.reassignments ?? 0}
        />
        <StatCard
          title="Effective work"
          value={formatDuration(totals.effectiveWorkSeconds)}
        />
        <StatCard
          title="Average resolution"
          value={formatDuration(totals.averageResolutionSeconds)}
        />
      </div>

      <div className="dashboard-grid">
        <section className="dashboard-panel">
          <div className="panel-heading">
            <h2>Tickets by status</h2>
            <span>Selected period</span>
          </div>

          <div className="status-bars">
            {statusRows.length === 0 ? (
              <p className="empty-panel">No status data.</p>
            ) : (
              statusRows.map((item) => (
                <div className="status-bar-row" key={item.name}>
                  <div>
                    <strong>
                      {item.name === "InProgress"
                        ? "In Progress"
                        : item.name}
                    </strong>
                    <span>{item.total}</span>
                  </div>
                  <div className="status-bar-track">
                    <span
                      style={{
                        width: `${
                          (Number(item.total) / largestStatusTotal) *
                          100
                        }%`,
                      }}
                    />
                  </div>
                </div>
              ))
            )}
          </div>
        </section>

        <section className="dashboard-panel">
          <div className="panel-heading">
            <h2>Categories</h2>
            <span>Ticket distribution</span>
          </div>

          <div className="category-list">
            {(summary?.byCategory || []).map((category) => (
              <div key={category.name}>
                <span>{category.name}</span>
                <strong>{category.total}</strong>
              </div>
            ))}
          </div>
        </section>
      </div>

      <section className="dashboard-panel agent-panel">
        <div className="panel-heading">
          <h2>Agent performance</h2>
          <span>Assignments and effective working time</span>
        </div>

        <div className="dashboard-table-scroll">
          <table>
            <thead>
              <tr>
                <th>Agent</th>
                <th>Current assigned</th>
                <th>In progress</th>
                <th>Resolved</th>
                <th>Assignment history</th>
                <th>Work sessions</th>
                <th>Effective work</th>
              </tr>
            </thead>
            <tbody>
              {agents.map((agent) => (
                <tr key={agent.id}>
                  <td>
                    <strong>{agentName(agent)}</strong>
                    <small>{agent.email}</small>
                  </td>
                  <td>{agent.currentAssignedTicketsCount ?? 0}</td>
                  <td>{agent.inProgressTicketsCount ?? 0}</td>
                  <td>{agent.resolvedTicketsCount ?? 0}</td>
                  <td>{agent.assignmentHistoryCount ?? 0}</td>
                  <td>{agent.workSessionCount ?? 0}</td>
                  <td>
                    {formatDuration(agent.effectiveWorkSeconds)}
                  </td>
                </tr>
              ))}

              {!isLoading && agents.length === 0 && (
                <tr>
                  <td colSpan="7" className="empty-table">
                    No active Support Agents.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <RecentTicketsTable
        tickets={recentTickets}
        isLoading={isLoading}
      />
    </DashboardLayout>
  );
}

export default DashboardPage;