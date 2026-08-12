import { useCallback, useEffect, useState } from "react";

import {
  getDashboardSummary,
  getRecentTickets,
} from "../api/dashboardApi";
import DashboardCharts from "../components/Dashboard/DashboardCharts";
import DashboardLayout from "../components/Dashboard/DashboardLayout";
import RecentTicketsTable from "../components/Dashboard/RecentTicketsTable";
import StatCard from "../components/Dashboard/StatCard";
import { useAuth } from "../context/AuthContext";
import ExportButtons from "../components/Reports/ExportButtons";

function formatDuration(seconds) {
  const value = Math.max(0, Number(seconds) || 0);
  const hours = Math.floor(value / 3600);
  const minutes = Math.floor((value % 3600) / 60);

  if (hours > 0) return `${hours}h ${minutes}m`;
  return `${minutes}m`;
}

function localDateValue(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function agentName(agent) {
  return (
    agent.fullName ||
    `${agent.firstName || ""} ${agent.lastName || ""}`.trim() ||
    agent.email
  );
}

function DashboardPage() {
  const { user } = useAuth();
  const role =
    user?.role?.roleName || user?.roleName || user?.role || "";
  const isAgent = role === "SupportAgent";

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

      const [summaryResponse, ticketsResponse] = await Promise.all([
        getDashboardSummary(appliedPeriod),
        getRecentTickets(),
      ]);

      setSummary(summaryResponse?.data || null);

      const paginator = ticketsResponse?.data || {};
      setRecentTickets(
        Array.isArray(paginator.data) ? paginator.data.slice(0, 5) : []
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
  const agents = summary?.agentPerformance || [];

  function applyPeriod(event) {
    event.preventDefault();

    if (dateFrom && dateTo && dateTo < dateFrom) {
      setErrorMessage("The end date must be on or after the start date.");
      return;
    }

    setAppliedPeriod({ dateFrom, dateTo });
  }

  function selectQuickPeriod(monthCount) {
    const today = new Date();
    const start = new Date(
      today.getFullYear(),
      today.getMonth() - monthCount + 1,
      1
    );
    const nextPeriod = {
      dateFrom: localDateValue(start),
      dateTo: localDateValue(today),
    };

    setDateFrom(nextPeriod.dateFrom);
    setDateTo(nextPeriod.dateTo);
    setAppliedPeriod(nextPeriod);
    setErrorMessage("");
  }

  function clearPeriod() {
    setDateFrom("");
    setDateTo("");
    setAppliedPeriod({ dateFrom: "", dateTo: "" });
    setErrorMessage("");
  }

  return (
    <DashboardLayout>
      <div className="dashboard-heading-row">
        <div>
          <h1>{isAgent ? "My ticket performance" : "Ticket operations"}</h1>
          <p>
            {isAgent
              ? "Your assigned workload, progress and service-time overview."
              : "Workflow, workload and service-time overview."}
          </p>
          <ExportButtons filters={appliedPeriod} />
        </div>

        <form className="report-period" onSubmit={applyPeriod}>
          <div className="quick-periods">
            <button type="button" onClick={() => selectQuickPeriod(1)}>
              This month
            </button>
            <button type="button" onClick={() => selectQuickPeriod(2)}>
              Last 2 months
            </button>
          </div>

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

          <button type="submit">Apply</button>
          <button type="button" className="clear-period" onClick={clearPeriod}>
            Clear
          </button>
        </form>
      </div>

      {errorMessage && (
        <div className="dashboard-alert">
          <span>{errorMessage}</span>
          <button type="button" onClick={loadDashboard}>Retry</button>
        </div>
      )}

      <div className="stats-grid">
        <StatCard title="Total tickets" value={totals.tickets ?? 0} />
        <StatCard title="Resolved" value={totals.resolved ?? 0} />
        <StatCard title="Closed" value={totals.closed ?? 0} />
        <StatCard title="Cancelled" value={totals.cancelled ?? 0} />
        <StatCard title="Unassigned" value={totals.unassigned ?? 0} tone="warning" />
        <StatCard title="Overdue" value={totals.overdue ?? 0} tone="danger" />
        <StatCard title="Escalated" value={totals.escalated ?? 0} tone="danger" />
        <StatCard title="Reassignments" value={totals.reassignments ?? 0} />
        <StatCard
          title="Effective work"
          value={formatDuration(totals.effectiveWorkSeconds)}
        />
        <StatCard
          title="Average resolution"
          value={formatDuration(totals.averageResolutionSeconds)}
        />
      </div>

      <DashboardCharts
        byStatus={summary?.byStatus}
        byPriority={summary?.byPriority}
        byCategory={summary?.byCategory}
        monthlyTrend={summary?.monthlyTrend}
      />

      <section className="dashboard-panel agent-panel">
        <div className="panel-heading">
          <div>
            <h2>{isAgent ? "My performance" : "Agent performance"}</h2>
            <span>Assignments and effective working time</span>
          </div>
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
                  <td><strong>{agentName(agent)}</strong><small>{agent.email}</small></td>
                  <td>{agent.currentAssignedTicketsCount ?? 0}</td>
                  <td>{agent.inProgressTicketsCount ?? 0}</td>
                  <td>{agent.resolvedTicketsCount ?? 0}</td>
                  <td>{agent.assignmentHistoryCount ?? 0}</td>
                  <td>{agent.workSessionCount ?? 0}</td>
                  <td>{formatDuration(agent.effectiveWorkSeconds)}</td>
                </tr>
              ))}
              {!isLoading && agents.length === 0 && (
                <tr><td colSpan="7" className="empty-table">No performance data.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <RecentTicketsTable tickets={recentTickets} isLoading={isLoading} />
    </DashboardLayout>
  );
}

export default DashboardPage;