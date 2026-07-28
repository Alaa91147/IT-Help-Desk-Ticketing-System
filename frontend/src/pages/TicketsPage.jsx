import {
  useCallback,
  useEffect,
  useState,
} from "react";
import { useNavigate } from "react-router";

import { getCategories } from "../api/lookupApi";
import { getTickets } from "../api/ticketApi";
import { useAuth } from "../context/AuthContext";

function extractArray(response) {
  if (Array.isArray(response)) {
    return response;
  }

  if (Array.isArray(response?.data)) {
    return response.data;
  }

  if (Array.isArray(response?.data?.data)) {
    return response.data.data;
  }

  return [];
}

function TicketsPage() {
  const navigate = useNavigate();
  const { token, user, logout } = useAuth();

  const [tickets, setTickets] = useState([]);
  const [categories, setCategories] = useState([]);

  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [priority, setPriority] = useState("");
  const [category, setCategory] = useState("");
  const [date, setDate] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [sortBy, setSortBy] = useState("createdAt");
  const [sortDirection, setSortDirection] =
    useState("desc");

  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });

  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] =
    useState("");

  const userRole =
    user?.role?.roleName ||
    user?.roleName ||
    user?.role ||
    "";

  const canCreateTicket =
    userRole === "Admin" || userRole === "User";

  const loadCategories = useCallback(async () => {
    try {
      const response = await getCategories(token);
      setCategories(extractArray(response));
    } catch (error) {
      console.error(
        "Could not load categories:",
        error
      );
    }
  }, [token]);

  const loadTickets = useCallback(
    async (page = 1) => {
      try {
        setIsLoading(true);
        setErrorMessage("");

        const response = await getTickets(token, {
          page,
          search,
          status,
          priority,
          category,
          date,
          dateFrom,
          dateTo,
          sortBy,
          sortDirection,
          perPage: 10,
        });

        const paginator = response?.data || {};

        setTickets(
          Array.isArray(paginator?.data)
            ? paginator.data
            : []
        );

        setPagination({
          currentPage: paginator?.current_page || 1,
          lastPage: paginator?.last_page || 1,
          total: paginator?.total || 0,
        });
      } catch (error) {
        setTickets([]);

        if (error.status === 401) {
          setErrorMessage(
            "Your session has expired. Please log in again."
          );
        } else if (error.status === 403) {
          setErrorMessage(
            error.message ||
              "You do not have permission to view tickets."
          );
        } else {
          setErrorMessage(
            error.message ||
              "Unable to load tickets."
          );
        }
      } finally {
        setIsLoading(false);
      }
    },
    [
      token,
      search,
      status,
      priority,
      category,
      date,
      dateFrom,
      dateTo,
      sortBy,
      sortDirection,
    ]
  );

  useEffect(() => {
    loadCategories();
  }, [loadCategories]);

  useEffect(() => {
    loadTickets(1);
  }, [loadTickets]);

  function handleSearch(event) {
    event.preventDefault();
    setSearch(searchInput.trim());
  }

  function handleClearFilters() {
    setSearchInput("");
    setSearch("");
    setStatus("");
    setPriority("");
    setCategory("");
    setDate("");
    setDateFrom("");
    setDateTo("");
    setSortBy("createdAt");
    setSortDirection("desc");
  }

  async function handleLogout() {
    await logout();
    navigate("/login", { replace: true });
  }

  function formatDate(dateValue) {
    if (!dateValue) {
      return "—";
    }

    return new Date(dateValue).toLocaleString(
      "en-GB",
      {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }
    );
  }

  function isOverdue(ticket) {
    if (!ticket?.dueAt) {
      return false;
    }

    const statusName = ticket?.status?.statusName;

    return (
      !["Resolved", "Closed"].includes(statusName) &&
      new Date(ticket.dueAt).getTime() < Date.now()
    );
  }

  function getAssignedUserName(ticket) {
    const assignedUser = ticket?.assignedUser;

    if (!assignedUser) {
      return "Unassigned";
    }

    return (
      assignedUser.fullName ||
      `${assignedUser.firstName || ""} ${
        assignedUser.lastName || ""
      }`.trim() ||
      assignedUser.email ||
      "Assigned"
    );
  }

  return (
    <div style={styles.page}>
      <header style={styles.header}>
        <div>
          <h1 style={styles.title}>Tickets</h1>
          <p style={styles.subtitle}>
            View and manage help desk requests
          </p>
        </div>

        <div style={styles.headerActions}>
          {(userRole === "Admin" ||
            userRole === "Manager") && (
            <button
              type="button"
              style={styles.secondaryButton}
              onClick={() =>
                navigate("/dashboard")
              }
            >
              Dashboard
            </button>
          )}

          {canCreateTicket && (
            <button
              type="button"
              style={styles.primaryButton}
              onClick={() =>
                navigate("/tickets/create")
              }
            >
              + Create Ticket
            </button>
          )}

          <button
            type="button"
            style={styles.logoutButton}
            onClick={handleLogout}
          >
            Log out
          </button>
        </div>
      </header>

      <section style={styles.userCard}>
        <div>
          <strong>
            {user?.fullName ||
              `${user?.firstName || ""} ${
                user?.lastName || ""
              }`.trim() ||
              "User"}
          </strong>

          <span style={styles.roleBadge}>
            {userRole === "SupportAgent"
              ? "Support Agent"
              : userRole}
          </span>
        </div>

        <span style={styles.email}>
          {user?.email}
        </span>
      </section>

      <section style={styles.filtersCard}>
        <form
          style={styles.filters}
          onSubmit={handleSearch}
        >
          <input
            type="text"
            value={searchInput}
            onChange={(event) =>
              setSearchInput(event.target.value)
            }
            placeholder="Search ticket number, subject or description"
            style={styles.searchInput}
          />

          <select
            value={status}
            onChange={(event) =>
              setStatus(event.target.value)
            }
            style={styles.select}
          >
            <option value="">All statuses</option>
            <option value="Open">Open</option>
            <option value="Assigned">
              Assigned
            </option>
            <option value="InProgress">
              In Progress
            </option>
            <option value="Resolved">
              Resolved
            </option>
            <option value="Closed">Closed</option>
          </select>

          <select
            value={priority}
            onChange={(event) =>
              setPriority(event.target.value)
            }
            style={styles.select}
          >
            <option value="">All priorities</option>
            <option value="Low">Low</option>
            <option value="Medium">Medium</option>
            <option value="High">High</option>
            <option value="Critical">
              Critical
            </option>
          </select>

          <select
            value={category}
            onChange={(event) =>
              setCategory(event.target.value)
            }
            style={styles.select}
          >
            <option value="">All categories</option>

            {categories.map((item) => (
              <option
                key={item.id}
                value={item.id}
              >
                {item.categoryName}
              </option>
            ))}
          </select>

          <label style={styles.dateField}>
            <span style={styles.dateLabel}>Exact date</span>
            <input
              type="date"
              value={date}
              onChange={(event) => {
                setDate(event.target.value);
                if (event.target.value) {
                  setDateFrom("");
                  setDateTo("");
                }
              }}
              style={styles.dateInput}
            />
          </label>

          <label style={styles.dateField}>
            <span style={styles.dateLabel}>From</span>
            <input
              type="date"
              value={dateFrom}
              max={dateTo || undefined}
              onChange={(event) => {
                setDateFrom(event.target.value);
                if (event.target.value) {
                  setDate("");
                }
              }}
              style={styles.dateInput}
            />
          </label>

          <label style={styles.dateField}>
            <span style={styles.dateLabel}>To</span>
            <input
              type="date"
              value={dateTo}
              min={dateFrom || undefined}
              onChange={(event) => {
                setDateTo(event.target.value);
                if (event.target.value) {
                  setDate("");
                }
              }}
              style={styles.dateInput}
            />
          </label>

          <select
            value={sortBy}
            onChange={(event) =>
              setSortBy(event.target.value)
            }
            style={styles.select}
          >
            <option value="createdAt">
              Sort by created date
            </option>
            <option value="updatedAt">
              Sort by updated date
            </option>
            <option value="ticketNumber">
              Sort by ticket number
            </option>
            <option value="subject">
              Sort by subject
            </option>
          </select>

          <select
            value={sortDirection}
            onChange={(event) =>
              setSortDirection(
                event.target.value
              )
            }
            style={styles.select}
          >
            <option value="desc">
              Newest first
            </option>
            <option value="asc">
              Oldest first
            </option>
          </select>

          <button
            type="submit"
            style={styles.primaryButton}
          >
            Search
          </button>

          <button
            type="button"
            style={styles.secondaryButton}
            onClick={handleClearFilters}
          >
            Clear
          </button>
        </form>
      </section>

      {errorMessage && (
        <div style={styles.errorAlert}>
          <span>{errorMessage}</span>

          <button
            type="button"
            style={styles.retryButton}
            onClick={() =>
              loadTickets(
                pagination.currentPage
              )
            }
          >
            Try again
          </button>
        </div>
      )}

      <section style={styles.tableCard}>
        <div style={styles.tableHeader}>
          <h2 style={styles.sectionTitle}>
            Ticket List
          </h2>

          <p style={styles.ticketCount}>
            {pagination.total} ticket
            {pagination.total === 1 ? "" : "s"}
          </p>
        </div>

        {isLoading ? (
          <div style={styles.emptyState}>
            <p>Loading tickets...</p>
          </div>
        ) : tickets.length === 0 ? (
          <div style={styles.emptyState}>
            <div style={styles.emptyIcon}>🎫</div>

            <h3 style={styles.emptyTitle}>
              No tickets found
            </h3>

            <p style={styles.emptyText}>
              {search ||
              status ||
              priority ||
              category ||
              date ||
              dateFrom ||
              dateTo
                ? "No tickets match the selected search or filters."
                : "There are currently no tickets available."}
            </p>

            {canCreateTicket && (
              <button
                type="button"
                style={styles.primaryButton}
                onClick={() =>
                  navigate("/tickets/create")
                }
              >
                Create your first ticket
              </button>
            )}
          </div>
        ) : (
          <>
            <div style={styles.tableWrapper}>
              <table style={styles.table}>
                <thead>
                  <tr>
                    <th style={styles.th}>
                      Ticket
                    </th>
                    <th style={styles.th}>
                      Subject
                    </th>
                    <th style={styles.th}>
                      Category
                    </th>
                    <th style={styles.th}>
                      Priority
                    </th>
                    <th style={styles.th}>
                      Status
                    </th>
                    <th style={styles.th}>
                      Assigned To
                    </th>
                    <th style={styles.th}>
                      Created
                    </th>
                    <th style={styles.th}>
                      Assigned At
                    </th>
                    <th style={styles.th}>
                      Due At
                    </th>
                    <th style={styles.th}>
                      Action
                    </th>
                  </tr>
                </thead>

                <tbody>
                  {tickets.map((ticket) => {
                    const statusName =
                      ticket?.status
                        ?.statusName ||
                      "Unknown";

                    const priorityName =
                      ticket?.priority
                        ?.priorityName ||
                      "Unknown";

                    return (
                      <tr key={ticket.id}>
                        <td style={styles.td}>
                          <strong>
                            {
                              ticket.ticketNumber
                            }
                          </strong>
                        </td>

                        <td style={styles.td}>
                          {ticket.subject}
                        </td>

                        <td style={styles.td}>
                          {ticket?.category
                            ?.categoryName || "—"}
                        </td>

                        <td style={styles.td}>
                          <span
                            style={styles.badge}
                          >
                            {priorityName}
                          </span>
                        </td>

                        <td style={styles.td}>
                          <span
                            style={styles.badge}
                          >
                            {formatStatus(
                              statusName
                            )}
                          </span>
                        </td>

                        <td style={styles.td}>
                          {getAssignedUserName(
                            ticket
                          )}
                        </td>

                        <td style={styles.td}>
                          {formatDate(
                            ticket.createdAt
                          )}
                        </td>

                        <td style={styles.td}>
                          {formatDate(
                            ticket.assignedAt
                          )}
                        </td>

                        <td
                          style={{
                            ...styles.td,
                            ...(isOverdue(ticket)
                              ? styles.overdue
                              : {}),
                          }}
                        >
                          {formatDate(ticket.dueAt)}
                          {isOverdue(ticket) && (
                            <span style={styles.overdueBadge}>
                              Overdue
                            </span>
                          )}
                        </td>

                        <td style={styles.td}>
                          <button
                            type="button"
                            style={
                              styles.viewButton
                            }
                            onClick={() =>
                              navigate(
                                `/tickets/${ticket.id}`
                              )
                            }
                          >
                            View
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            <div style={styles.pagination}>
              <button
                type="button"
                style={styles.secondaryButton}
                disabled={
                  pagination.currentPage <= 1
                }
                onClick={() =>
                  loadTickets(
                    pagination.currentPage - 1
                  )
                }
              >
                Previous
              </button>

              <span>
                Page {pagination.currentPage} of{" "}
                {pagination.lastPage}
              </span>

              <button
                type="button"
                style={styles.secondaryButton}
                disabled={
                  pagination.currentPage >=
                  pagination.lastPage
                }
                onClick={() =>
                  loadTickets(
                    pagination.currentPage + 1
                  )
                }
              >
                Next
              </button>
            </div>
          </>
        )}
      </section>
    </div>
  );
}

function formatStatus(status) {
  return status === "InProgress"
    ? "In Progress"
    : status;
}

const styles = {
  page: {
    minHeight: "100vh",
    padding: "32px",
    backgroundColor: "#f4f7fb",
    fontFamily: "Arial, sans-serif",
  },

  header: {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    gap: "20px",
    marginBottom: "24px",
    flexWrap: "wrap",
  },

  title: {
    margin: 0,
    fontSize: "32px",
    color: "#172033",
  },

  subtitle: {
    margin: "6px 0 0",
    color: "#667085",
  },

  headerActions: {
    display: "flex",
    gap: "10px",
    flexWrap: "wrap",
  },

  primaryButton: {
    padding: "11px 18px",
    border: "none",
    borderRadius: "8px",
    backgroundColor: "#2563eb",
    color: "#ffffff",
    fontWeight: 600,
    cursor: "pointer",
  },

  secondaryButton: {
    padding: "10px 16px",
    border: "1px solid #d0d5dd",
    borderRadius: "8px",
    backgroundColor: "#ffffff",
    color: "#344054",
    fontWeight: 600,
    cursor: "pointer",
  },

  logoutButton: {
    padding: "10px 16px",
    border: "1px solid #fecaca",
    borderRadius: "8px",
    backgroundColor: "#ffffff",
    color: "#b42318",
    fontWeight: 600,
    cursor: "pointer",
  },

  userCard: {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    padding: "16px 20px",
    marginBottom: "20px",
    border: "1px solid #e4e7ec",
    borderRadius: "12px",
    backgroundColor: "#ffffff",
    flexWrap: "wrap",
    gap: "10px",
  },

  roleBadge: {
    display: "inline-block",
    marginLeft: "10px",
    padding: "4px 9px",
    borderRadius: "20px",
    backgroundColor: "#eef4ff",
    color: "#3538cd",
    fontSize: "12px",
    fontWeight: 600,
  },

  email: {
    color: "#667085",
  },

  filtersCard: {
    padding: "18px",
    marginBottom: "20px",
    border: "1px solid #e4e7ec",
    borderRadius: "12px",
    backgroundColor: "#ffffff",
  },

  filters: {
    display: "flex",
    gap: "12px",
    flexWrap: "wrap",
  },
  overdue: {
    color: "#b42318",
    fontWeight: 700,
  },
  overdueBadge: {
    display: "block",
    width: "fit-content",
    marginTop: "4px",
    padding: "2px 7px",
    borderRadius: "999px",
    backgroundColor: "#fee4e2",
    color: "#b42318",
    fontSize: "11px",
  },
  dateField: {
    display: "grid",
    gap: "4px",
    minWidth: "145px",
  },
  dateLabel: {
    color: "#667085",
    fontSize: "12px",
    fontWeight: 700,
  },
  dateInput: {
    minHeight: "42px",
    padding: "8px 10px",
    border: "1px solid #d0d5dd",
    borderRadius: "8px",
    backgroundColor: "#ffffff",
    color: "#344054",
  },

  searchInput: {
    flex: "1 1 300px",
    minWidth: "240px",
    padding: "11px 12px",
    border: "1px solid #d0d5dd",
    borderRadius: "8px",
  },

  select: {
    minWidth: "155px",
    padding: "11px 12px",
    border: "1px solid #d0d5dd",
    borderRadius: "8px",
    backgroundColor: "#ffffff",
  },

  errorAlert: {
    display: "flex",
    justifyContent: "space-between",
    padding: "14px 18px",
    marginBottom: "20px",
    border: "1px solid #fecaca",
    borderRadius: "10px",
    backgroundColor: "#fef2f2",
    color: "#b42318",
  },

  retryButton: {
    border: "none",
    backgroundColor: "transparent",
    color: "#b42318",
    fontWeight: 700,
    cursor: "pointer",
  },

  tableCard: {
    overflow: "hidden",
    border: "1px solid #e4e7ec",
    borderRadius: "12px",
    backgroundColor: "#ffffff",
  },

  tableHeader: {
    padding: "20px",
    borderBottom: "1px solid #e4e7ec",
  },

  sectionTitle: {
    margin: 0,
    color: "#172033",
  },

  ticketCount: {
    margin: "5px 0 0",
    color: "#667085",
  },

  tableWrapper: {
    overflowX: "auto",
  },

  table: {
    width: "100%",
    borderCollapse: "collapse",
  },

  th: {
    padding: "14px 16px",
    borderBottom: "1px solid #e4e7ec",
    backgroundColor: "#f9fafb",
    color: "#475467",
    fontSize: "12px",
    textAlign: "left",
  },

  td: {
    padding: "16px",
    borderBottom: "1px solid #eaecf0",
    color: "#344054",
    whiteSpace: "nowrap",
  },

  badge: {
    display: "inline-block",
    padding: "5px 9px",
    borderRadius: "20px",
    backgroundColor: "#eef2ff",
    color: "#3730a3",
    fontSize: "12px",
    fontWeight: 700,
  },

  viewButton: {
    border: "none",
    backgroundColor: "transparent",
    color: "#2563eb",
    fontWeight: 700,
    cursor: "pointer",
  },

  emptyState: {
    padding: "70px 20px",
    textAlign: "center",
    color: "#667085",
  },

  emptyIcon: {
    marginBottom: "12px",
    fontSize: "46px",
  },

  emptyTitle: {
    margin: "0 0 8px",
    color: "#172033",
  },

  emptyText: {
    margin: "0 0 20px",
  },

  pagination: {
    display: "flex",
    justifyContent: "center",
    alignItems: "center",
    gap: "18px",
    padding: "18px",
  },
};

export default TicketsPage;