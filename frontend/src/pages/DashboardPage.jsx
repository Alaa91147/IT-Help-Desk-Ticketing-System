import { useEffect, useState } from "react";

import DashboardLayout from "../components/Dashboard/DashboardLayout";
import RecentTicketsTable from "../components/Dashboard/RecentTicketsTable";
import StatCard from "../components/Dashboard/StatCard";
import { getDashboardSummary } from "../api/dashboardApi";

function DashboardPage() {
  const [summary, setSummary] = useState(null);

  useEffect(() => {
    async function loadDashboard() {
      try {
        const response = await getDashboardSummary();

        console.log("Dashboard Response:", response);
        console.log("Summary Data:", response.data);

        setSummary(response.data);
      } catch (error) {
        console.error("Dashboard Error:", error);

        if (error.data) {
          console.error("Error Response:", error.data);
        }
      }
    }

    loadDashboard();
  }, []);

  return (
    <DashboardLayout>
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

      <RecentTicketsTable />
    </DashboardLayout>
  );
}

export default DashboardPage;