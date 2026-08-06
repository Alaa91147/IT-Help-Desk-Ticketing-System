import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

const STATUS_COLORS = [
  "#2563eb",
  "#f79009",
  "#12b76a",
  "#7f56d9",
  "#d92d20",
  "#06aed4",
];

function normalizeData(data) {
  if (!Array.isArray(data)) return [];

  return data.map((item) => ({
    name: item.name === "InProgress" ? "In Progress" : item.name,
    total: Number(item.total) || 0,
  }));
}

function normalizeTrend(data) {
  if (!Array.isArray(data)) return [];

  return data.map((item) => ({
    label: item.label || item.month,
    Created: Number(item.created) || 0,
    Resolved: Number(item.resolved) || 0,
    Cancelled: Number(item.cancelled) || 0,
    Closed: Number(item.closed) || 0,
    "Average resolution (hours)":
      Math.round(((Number(item.averageResolutionSeconds) || 0) / 3600) * 10) / 10,
  }));
}

function EmptyChart() {
  return <p className="empty-panel">No chart data available.</p>;
}

function DashboardCharts({
  byStatus = [],
  byPriority = [],
  byCategory = [],
  monthlyTrend = [],
}) {
  const statusData = normalizeData(byStatus);
  const priorityData = normalizeData(byPriority);
  const categoryData = normalizeData(byCategory);
  const trendData = normalizeTrend(monthlyTrend);

  return (
    <div className="analytics-grid">
      <section className="dashboard-panel chart-panel">
        <div className="panel-heading"><div><h2>Tickets by status</h2><span>Current ticket distribution</span></div></div>
        <div className="chart-container">
          {statusData.length === 0 ? <EmptyChart /> : (
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie data={statusData} dataKey="total" nameKey="name" cx="50%" cy="45%" innerRadius={55} outerRadius={90} paddingAngle={3}>
                  {statusData.map((item, index) => (
                    <Cell key={item.name} fill={STATUS_COLORS[index % STATUS_COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip /><Legend />
              </PieChart>
            </ResponsiveContainer>
          )}
        </div>
      </section>

      <section className="dashboard-panel chart-panel">
        <div className="panel-heading"><div><h2>Tickets by priority</h2><span>Work urgency distribution</span></div></div>
        <div className="chart-container">
          {priorityData.length === 0 ? <EmptyChart /> : (
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={priorityData} margin={{ top: 10, right: 10, left: -15, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="name" /><YAxis allowDecimals={false} /><Tooltip />
                <Bar dataKey="total" name="Tickets" fill="#f79009" radius={[6, 6, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </div>
      </section>

      <section className="dashboard-panel chart-panel category-chart-panel">
        <div className="panel-heading"><div><h2>Tickets by category</h2><span>Most common support requests</span></div></div>
        <div className="chart-container category-chart-container">
          {categoryData.length === 0 ? <EmptyChart /> : (
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={categoryData} layout="vertical" margin={{ top: 5, right: 20, left: 20, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                <XAxis type="number" allowDecimals={false} />
                <YAxis type="category" dataKey="name" width={90} /><Tooltip />
                <Bar dataKey="total" name="Tickets" fill="#2563eb" radius={[0, 6, 6, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </div>
      </section>

      <section className="dashboard-panel chart-panel category-chart-panel">
        <div className="panel-heading"><div><h2>Monthly ticket results</h2><span>Previous month compared with this month</span></div></div>
        <div className="chart-container category-chart-container">
          {trendData.length === 0 ? <EmptyChart /> : (
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={trendData} margin={{ top: 10, right: 25, left: 0, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="label" /><YAxis allowDecimals={false} /><Tooltip /><Legend />
                <Line type="monotone" dataKey="Created" stroke="#2563eb" strokeWidth={3} />
                <Line type="monotone" dataKey="Resolved" stroke="#12b76a" strokeWidth={3} />
                <Line type="monotone" dataKey="Closed" stroke="#7f56d9" strokeWidth={3} />
                <Line type="monotone" dataKey="Cancelled" stroke="#d92d20" strokeWidth={3} />
              </LineChart>
            </ResponsiveContainer>
          )}
        </div>
      </section>
    </div>
  );
}

export default DashboardCharts;