import { useEffect, useState } from "react";
import { useNavigate } from "react-router";

import {
  getCategories,
  getPriorities,
} from "../api/lookupApi";
import { createTicket } from "../api/ticketApi";
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

function CreateTicketPage() {
  const navigate = useNavigate();
  const { token } = useAuth();

  const [categories, setCategories] = useState([]);
  const [priorities, setPriorities] = useState([]);

  const [formData, setFormData] = useState({
    categoryId: "",
    priorityId: "",
    subject: "",
    description: "",
  });

  const [errors, setErrors] = useState({});
  const [serverMessage, setServerMessage] =
    useState("");
  const [isLoadingLookups, setIsLoadingLookups] =
    useState(true);
  const [isSubmitting, setIsSubmitting] =
    useState(false);

  useEffect(() => {
    async function loadLookups() {
      try {
        setIsLoadingLookups(true);

        const [categoryResponse, priorityResponse] =
          await Promise.all([
            getCategories(token),
            getPriorities(token),
          ]);

        setCategories(extractArray(categoryResponse));
        setPriorities(extractArray(priorityResponse));
      } catch (error) {
        setServerMessage(
          error.message ||
            "Unable to load categories and priorities."
        );
      } finally {
        setIsLoadingLookups(false);
      }
    }

    loadLookups();
  }, [token]);

  function handleChange(event) {
    const { name, value } = event.target;

    setFormData((current) => ({
      ...current,
      [name]: value,
    }));

    setErrors((current) => ({
      ...current,
      [name]: "",
    }));

    setServerMessage("");
  }

  function validateForm() {
    const validationErrors = {};

    if (!formData.categoryId) {
      validationErrors.categoryId =
        "Please select a category.";
    }

    if (!formData.priorityId) {
      validationErrors.priorityId =
        "Please select a priority.";
    }

    if (!formData.subject.trim()) {
      validationErrors.subject =
        "Subject is required.";
    } else if (formData.subject.trim().length > 255) {
      validationErrors.subject =
        "Subject cannot exceed 255 characters.";
    }

    if (!formData.description.trim()) {
      validationErrors.description =
        "Description is required.";
    }

    return validationErrors;
  }

  async function handleSubmit(event) {
    event.preventDefault();

    const validationErrors = validateForm();

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }

    try {
      setIsSubmitting(true);
      setServerMessage("");

      const response = await createTicket(
        formData,
        token
      );

      const createdTicket = response?.data;

      if (createdTicket?.id) {
        navigate(`/tickets/${createdTicket.id}`, {
          replace: true,
        });
      } else {
        navigate("/tickets", {
          replace: true,
        });
      }
    } catch (error) {
      const backendErrors = error?.data?.errors;

      if (backendErrors) {
        setErrors({
          categoryId:
            backendErrors.categoryId?.[0] || "",
          priorityId:
            backendErrors.priorityId?.[0] || "",
          subject: backendErrors.subject?.[0] || "",
          description:
            backendErrors.description?.[0] || "",
        });
      }

      setServerMessage(
        error.message || "Unable to create ticket."
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div style={styles.page}>
      <div style={styles.container}>
        <button
          type="button"
          style={styles.backButton}
          onClick={() => navigate("/tickets")}
        >
          ← Back to Tickets
        </button>

        <div style={styles.card}>
          <div style={styles.heading}>
            <h1 style={styles.title}>
              Create Ticket
            </h1>

            <p style={styles.subtitle}>
              Describe your technical issue and select
              its category and priority.
            </p>
          </div>

          {serverMessage && (
            <div style={styles.errorAlert}>
              {serverMessage}
            </div>
          )}

          {isLoadingLookups ? (
            <p>Loading form information...</p>
          ) : (
            <form
              style={styles.form}
              onSubmit={handleSubmit}
              noValidate
            >
              <div style={styles.field}>
                <label
                  htmlFor="categoryId"
                  style={styles.label}
                >
                  Category
                </label>

                <select
                  id="categoryId"
                  name="categoryId"
                  value={formData.categoryId}
                  onChange={handleChange}
                  style={styles.input}
                >
                  <option value="">
                    Select a category
                  </option>

                  {categories.map((category) => (
                    <option
                      key={category.id}
                      value={category.id}
                    >
                      {category.categoryName}
                    </option>
                  ))}
                </select>

                {errors.categoryId && (
                  <span style={styles.errorText}>
                    {errors.categoryId}
                  </span>
                )}
              </div>

              <div style={styles.field}>
                <label
                  htmlFor="priorityId"
                  style={styles.label}
                >
                  Priority
                </label>

                <select
                  id="priorityId"
                  name="priorityId"
                  value={formData.priorityId}
                  onChange={handleChange}
                  style={styles.input}
                >
                  <option value="">
                    Select a priority
                  </option>

                  {priorities.map((priority) => (
                    <option
                      key={priority.id}
                      value={priority.id}
                    >
                      {priority.priorityName}
                    </option>
                  ))}
                </select>

                {errors.priorityId && (
                  <span style={styles.errorText}>
                    {errors.priorityId}
                  </span>
                )}
              </div>

              <div style={styles.field}>
                <label
                  htmlFor="subject"
                  style={styles.label}
                >
                  Subject
                </label>

                <input
                  id="subject"
                  name="subject"
                  type="text"
                  value={formData.subject}
                  onChange={handleChange}
                  placeholder="Example: Cannot connect to Wi-Fi"
                  style={styles.input}
                  maxLength={255}
                />

                {errors.subject && (
                  <span style={styles.errorText}>
                    {errors.subject}
                  </span>
                )}
              </div>

              <div style={styles.field}>
                <label
                  htmlFor="description"
                  style={styles.label}
                >
                  Description
                </label>

                <textarea
                  id="description"
                  name="description"
                  value={formData.description}
                  onChange={handleChange}
                  placeholder="Explain the issue, when it started, and any error messages you received."
                  style={styles.textarea}
                  rows={8}
                />

                {errors.description && (
                  <span style={styles.errorText}>
                    {errors.description}
                  </span>
                )}
              </div>

              <div style={styles.actions}>
                <button
                  type="button"
                  style={styles.cancelButton}
                  onClick={() => navigate("/tickets")}
                  disabled={isSubmitting}
                >
                  Cancel
                </button>

                <button
                  type="submit"
                  style={styles.submitButton}
                  disabled={isSubmitting}
                >
                  {isSubmitting
                    ? "Creating..."
                    : "Create Ticket"}
                </button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}

const styles = {
  page: {
    minHeight: "100vh",
    padding: "32px 20px",
    backgroundColor: "#f4f7fb",
    fontFamily: "Arial, sans-serif",
  },

  container: {
    maxWidth: "760px",
    margin: "0 auto",
  },

  backButton: {
    marginBottom: "18px",
    border: "none",
    backgroundColor: "transparent",
    color: "#2563eb",
    fontWeight: 700,
    cursor: "pointer",
  },

  card: {
    padding: "32px",
    border: "1px solid #e4e7ec",
    borderRadius: "14px",
    backgroundColor: "#ffffff",
  },

  heading: {
    marginBottom: "26px",
  },

  title: {
    margin: 0,
    color: "#172033",
  },

  subtitle: {
    margin: "8px 0 0",
    color: "#667085",
  },

  form: {
    display: "flex",
    flexDirection: "column",
    gap: "20px",
  },

  field: {
    display: "flex",
    flexDirection: "column",
    gap: "7px",
  },

  label: {
    color: "#344054",
    fontWeight: 700,
  },

  input: {
    padding: "12px",
    border: "1px solid #d0d5dd",
    borderRadius: "8px",
    backgroundColor: "#ffffff",
  },

  textarea: {
    padding: "12px",
    border: "1px solid #d0d5dd",
    borderRadius: "8px",
    resize: "vertical",
    fontFamily: "inherit",
  },

  errorText: {
    color: "#b42318",
    fontSize: "13px",
  },

  errorAlert: {
    padding: "13px",
    marginBottom: "20px",
    border: "1px solid #fecaca",
    borderRadius: "8px",
    backgroundColor: "#fef2f2",
    color: "#b42318",
  },

  actions: {
    display: "flex",
    justifyContent: "flex-end",
    gap: "12px",
    marginTop: "8px",
  },

  cancelButton: {
    padding: "11px 18px",
    border: "1px solid #d0d5dd",
    borderRadius: "8px",
    backgroundColor: "#ffffff",
    color: "#344054",
    fontWeight: 700,
    cursor: "pointer",
  },

  submitButton: {
    padding: "11px 18px",
    border: "none",
    borderRadius: "8px",
    backgroundColor: "#2563eb",
    color: "#ffffff",
    fontWeight: 700,
    cursor: "pointer",
  },
};

export default CreateTicketPage;