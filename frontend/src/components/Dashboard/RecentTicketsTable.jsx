function RecentTicketsTable() {
  return (
    <div className="tickets-table">
      <h2>Recent Tickets</h2>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Priority</th>
          </tr>
        </thead>

        <tbody></tbody>
      </table>
    </div>
  );
}

export default RecentTicketsTable;