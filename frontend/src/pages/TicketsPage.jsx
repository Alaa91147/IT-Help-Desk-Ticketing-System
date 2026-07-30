import {
  useCallback,
  useEffect,
  useState,
} from "react";
import { useNavigate } from "react-router";

import { getCategories } from "../api/lookupApi";
import { getTickets } from "../api/ticketApi";
import NotificationBell from "../components/Notifications/NotificationBell";
import { useAuth } from "../context/AuthContext";
import "../styles/tickets.css";

function responseArray(response) {
  if (Array.isArray(response?.data?.data)) {
    return response.data.data;
  }
  if (Array.isArray(response?.data)) return response.data;
  if (Array.isArray(response)) return response;
  return [];
}

function roleOf(user) {
  return (
    user?.role?.roleName ||
    user?.roleName ||
    user?.role ||
    ""
  );
}

function fullName(user, fallback = "Unassigned") {
  if (!user) return fallback;

  return (
    user.fullName ||
    `${user.firstName || ""} ${user.lastName || ""}`.trim() ||
    user.email ||
    fallback
  );
}

function assignedUserOf(ticket) {
  return ticket?.assignedUser || ticket?.assigned_user || null;
}

function formatDate(value) {
  if (!value) return "—";

  return new Date(value).toLocaleString("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function labelStatus(status) {
  return status === "InProgress" ? "In Progress" : status;
}

function TicketsPage() {
  const navigate = useNavigate();
  const { token, user, logout } = useAuth();
  const role = roleOf(user);

  const [tickets, setTickets] = useState([]);
  const [categories, setCategories] = useState([]);
  const [searchInput, setSearchInput] = useState("");
  const [filters, setFilters] = useState({
    search: "",
    status: "",
    priority: "",
    category: "",
    date: "",
    dateFrom: "",
    dateTo: "",
    sortBy: "createdAt",
    sortDirection: "desc",
  });
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  const canCreate = role === "Admin" || role === "User";
  const canOpenDashboard =
    role === "Admin" || role === "Manager";

  const loadTickets = useCallback(
    async (page = 1) => {
      try {
        setIsLoading(true);
        setErrorMessage("");

        const response = await getTickets(token, {
          ...filters,
          page,
          perPage: 10,
        });

        const paginator = response?.data || {};

        setTickets(
          Array.isArray(paginator.data) ? paginator.data : []
        );
        setPagination({
          currentPage: paginator.current_page || 1,
          lastPage: paginator.last_page || 1,
          total: paginator.total || 0,
        });
      } catch (error) {
        setTickets([]);
        setErrorMessage(
          error?.message || "Unable to load tickets."
        );
      } finally {
        setIsLoading(false);
      }
    },
    [filters, token]
  );

  useEffect(() => {
    loadTickets(1);
  }, [loadTickets]);

  useEffect(() => {
    getCategories(token)
      .then((response) =>
        setCategories(responseArray(response))
      )
      .catch(() => setCategories([]));
  }, [token]);

  function submitSearch(event) {
    event.preventDefault();
    setFilters((current) => ({
      ...current,
      search: searchInput.trim(),
    }));
  }

  function setFilter(name, value) {
    setFilters((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function clearFilters() {
    setSearchInput("");
    setFilters({
      search: "",
      status: "",
      priority: "",
      category: "",
      date: "",
      dateFrom: "",
      dateTo: "",
      sortBy: "createdAt",
      sortDirection: "desc",
    });
  }

  function isOverdue(ticket) {
    return Boolean(
      ticket.dueAt &&
        !["Resolved", "Closed", "Cancelled"].includes(
          ticket.status?.statusName
        ) &&
        new Date(ticket.dueAt).getTime() < Date.now()
    );
  }

  async function handleLogout() {
    await logout();
    navigate("/login", { replace: true });
  }

  return (
    <main className="tickets-page">
      <header className="tickets-header">
        <div>
          <h1>Tickets</h1>
          <p>
            {role === "SupportAgent"
              ? "Review all requests and work on tickets assigned to you."
              : "View and manage help-desk requests."}
          </p>
        </div>

        <div className="tickets-header-actions">
          <NotificationBell />

          {canOpenDashboard && (
            <button
              className="button button-secondary"
              onClick={() => navigate("/dashboard")}
            >
              Dashboard
            </button>
          )}

          {canCreate && (
            <button
              className="button button-primary"
              onClick={() => navigate("/tickets/create")}
            >
              + Create ticket
            </button>
          )}

          <button
            className="button button-logout"
            onClick={handleLogout}
          >
            Log out
          </button>
        </div>
      </header>

      <section className="tickets-user-bar">
        <div>
          <strong>{fullName(user, "User")}</strong>
          <span>{role === "SupportAgent" ? "Support Agent" : role}</span>
        </div>
        <small>{user?.email}</small>
      </section>

      <form
        className="tickets-filters"
        onSubmit={submitSearch}
      >
        <input
          className="filter-search"
          value={searchInput}
          onChange={(event) =>
            setSearchInput(event.target.value)
          }
          placeholder="Search ticket number, subject or description"
        />

        <select
          value={filters.status}
          onChange={(event) =>
            setFilter("status", event.target.value)
          }
        >
          <option value="">All statuses</option>
          <option value="Open">Open</option>
          <option value="Assigned">Assigned</option>
          <option value="InProgress">In Progress</option>
          <option value="Escalated">Escalated</option>
          <option value="Resolved">Resolved</option>
          <option value="Closed">Closed</option>
          <option value="Cancelled">Cancelled</option>
        </select>

        <select
          value={filters.priority}
          onChange={(event) =>
            setFilter("priority", event.target.value)
          }
        >
          <option value="">All priorities</option>
          <option value="Low">Low</option>
          <option value="Medium">Medium</option>
          <option value="High">High</option>
          <option value="Urgent">Urgent</option>
        </select>

        <select
          value={filters.category}
          onChange={(event) =>
            setFilter("category", event.target.value)
          }
        >
          <option value="">All categories</option>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.categoryName}
            </option>
          ))}
        </select>

        <label>
          <span>Exact date</span>
          <input
            type="date"
            value={filters.date}
            onChange={(event) => {
              setFilter("date", event.target.value);
              if (event.target.value) {
                setFilter("dateFrom", "");
                setFilter("dateTo", "");
              }
            }}
          />
        </label>

        <label>
          <span>From</span>
          <input
            type="date"
            value={filters.dateFrom}
            max={filters.dateTo || undefined}
            onChange={(event) => {
              setFilter("dateFrom", event.target.value);
              if (event.target.value) {
                setFilter("date", "");
              }
            }}
          />
        </label>

        <label>
          <span>To</span>
          <input
            type="date"
            value={filters.dateTo}
            min={filters.dateFrom || undefined}
            onChange={(event) => {
              setFilter("dateTo", event.target.value);
              if (event.target.value) {
                setFilter("date", "");
              }
            }}
          />
        </label>

        <select
          value={filters.sortBy}
          onChange={(event) =>
            setFilter("sortBy", event.target.value)
          }
        >
          <option value="createdAt">Created date</option>
          <option value="updatedAt">Updated date</option>
          <option value="ticketNumber">Ticket number</option>
          <option value="subject">Subject</option>
        </select>

        <select
          value={filters.sortDirection}
          onChange={(event) =>
            setFilter("sortDirection", event.target.value)
          }
        >
          <option value="desc">Newest first</option>
          <option value="asc">Oldest first</option>
        </select>

        <button className="button button-primary">Search</button>
        <button
          type="button"
          className="button button-secondary"
          onClick={clearFilters}
        >
          Clear
        </button>
      </form>

      {errorMessage && (
        <div className="tickets-error">
          <span>{errorMessage}</span>
          <button
            onClick={() =>
              loadTickets(pagination.currentPage)
            }
          >
            Try again
          </button>
        </div>
      )}

      <section className="tickets-table-card">
        <div className="tickets-table-heading">
          <div>
            <h2>Ticket list</h2>
            <span>
              {pagination.total} ticket
              {pagination.total === 1 ? "" : "s"}
            </span>
          </div>
        </div>

        {isLoading ? (
          <div className="tickets-empty">Loading tickets…</div>
        ) : tickets.length === 0 ? (
          <div className="tickets-empty">
            <strong>No tickets found</strong>
            <p>Try changing the selected filters.</p>
          </div>
        ) : (
          <>
            <div className="tickets-table-scroll">
              <table className="tickets-table">
                <thead>
                  <tr>
                    <th>Ticket</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned to</th>
                    {role === "SupportAgent" && <th>Your access</th>}
                    <th>Created</th>
                    <th>Assigned at</th>
                    <th>Due at</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {tickets.map((ticket) => {
                    const currentAgent =
                      role === "SupportAgent" &&
                      Number(ticket.assignedUserId) ===
                        Number(user?.id);

                    return (
                      <tr key={ticket.id}>
                        <td>
                          <strong>{ticket.ticketNumber}</strong>
                        </td>
                        <td>{ticket.subject}</td>
                        <td>
                          {ticket.category?.categoryName || "—"}
                        </td>
                        <td>
                          <span
                            className={`data-pill priority-${(
                              ticket.priority?.priorityName || ""
                            ).toLowerCase()}`}
                          >
                            {ticket.priority?.priorityName || "—"}
                          </span>
                        </td>
                        <td>
                          <span
                            className={`data-pill status-${(
                              ticket.status?.statusName || ""
                            ).toLowerCase()}`}
                          >
                            {labelStatus(
                              ticket.status?.statusName
                            )}
                          </span>
                        </td>
                        <td>
                          {fullName(assignedUserOf(ticket))}
                        </td>
                        {role === "SupportAgent" && (
                          <td>
                            <span
                              className={`access-label ${
                                currentAgent
                                  ? "access-assigned"
                                  : ""
                              }`}
                            >
                              {currentAgent
                                ? "Assigned to you"
                                : "Read only"}
                            </span>
                          </td>
                        )}
                        <td>{formatDate(ticket.createdAt)}</td>
                        <td>{formatDate(ticket.assignedAt)}</td>
                        <td
                          className={
                            isOverdue(ticket) ? "is-overdue" : ""
                          }
                        >
                          {formatDate(ticket.dueAt)}
                          {isOverdue(ticket) && <small>Overdue</small>}
                        </td>
                        <td>
                          <button
                            className="table-link"
                            onClick={() =>
                              navigate(`/tickets/${ticket.id}`)
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

            <div className="tickets-pagination">
              <button
                className="button button-secondary"
                disabled={pagination.currentPage <= 1}
                onClick={() =>
                  loadTickets(pagination.currentPage - 1)
                }
              >
                Previous
              </button>
              <span>
                Page {pagination.currentPage} of{" "}
                {pagination.lastPage}
              </span>
              <button
                className="button button-secondary"
                disabled={
                  pagination.currentPage >= pagination.lastPage
                }
                onClick={() =>
                  loadTickets(pagination.currentPage + 1)
                }
              >
                Next
              </button>
            </div>
          </>
        )}
      </section>
    </main>
  );
}

export default TicketsPage;