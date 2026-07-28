import { useEffect, useState } from "react";

import DashboardLayout from "../components/Dashboard/DashboardLayout";
import RecentTicketsTable from "../components/Dashboard/RecentTicketsTable";
import StatCard from "../components/Dashboard/StatCard";
import {
  getDashboardSummary,
  getRecentTickets,
} from "../api/dashboardApi";

function DashboardPage() {
  const [summary, setSummary] = useState(null);
  const [recentTickets, setRecentTickets] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  useEffect(() => {
    async function loadDashboard() {
      try {
        setIsLoading(true);
        setErrorMessage("");
        const [summaryResponse, ticketsResponse] = await Promise.all([
          getDashboardSummary(),
          getRecentTickets(),
        ]);

        setSummary(summaryResponse?.data || null);

        const paginator = ticketsResponse?.data || {};
        const ticketItems = Array.isArray(paginator?.data)
          ? paginator.data
          : Array.isArray(paginator)
            ? paginator
            : [];

        setRecentTickets(ticketItems.slice(0, 5));
      } catch (error) {
        setErrorMessage(
          error.message || "Unable to load dashboard information."
        );
      } finally {
        setIsLoading(false);
      }
    }

    loadDashboard();
  }, []);

  return (
    <DashboardLayout>
      {errorMessage && (
        <div className="dashboard-alert">{errorMessage}</div>
      )}
      <div className="stats-grid">
        <StatCard
          title="Total Tickets"
          value={summary?.totals?.tickets ?? 0}
        />

        <StatCard
          title="Unassigned"
          value={summary?.totals?.unassigned ?? 0}
        />

        <StatCard
          title="Support Agents"
          value={summary?.agentPerformance?.length ?? 0}
        />

        <StatCard
          title="Categories"
          value={summary?.byCategory?.length ?? 0}
        />
      </div>

      <RecentTicketsTable
        tickets={recentTickets}
        isLoading={isLoading}
      />
    </DashboardLayout>
  );
}

export default DashboardPage;