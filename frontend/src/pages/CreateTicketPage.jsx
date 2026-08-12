import { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import {
  checkTicketDuplicates,
  createTicket,
  previewTicketClassification,
} from "../api/ticketApi";
import {
  getCategories,
  getPriorities,
} from "../api/lookupApi";
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
  const [duplicateMatches, setDuplicateMatches] =
    useState([]);
  const [duplicateChecked, setDuplicateChecked] =
    useState(false);
  const [checkingDuplicates, setCheckingDuplicates] =
    useState(false);
  const [allowDuplicateCreation, setAllowDuplicateCreation] =
    useState(false);
  const [formData, setFormData] = useState({
    categoryId: "",
    priorityId: "",
    subject: "",
    description: "",
  });
  const [
    classificationSuggestion,
    setClassificationSuggestion,
  ] = useState(null);
  const [classificationChecked, setClassificationChecked] =
    useState(false);
  const [
    checkingClassification,
    setCheckingClassification,
  ] = useState(false);
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

    if (["subject", "description"].includes(name)) {
      setDuplicateChecked(false);
      setDuplicateMatches([]);
      setAllowDuplicateCreation(false);
      setClassificationChecked(false);
      setClassificationSuggestion(null);
    }
  }

  function validateTicketText() {
    const validationErrors = {};

    if (!formData.subject.trim()) {
      validationErrors.subject = "Subject is required.";
    } else if (formData.subject.trim().length < 3) {
      validationErrors.subject =
        "Subject must contain at least 3 characters.";
    } else if (formData.subject.trim().length > 255) {
      validationErrors.subject =
        "Subject cannot exceed 255 characters.";
    }

    if (!formData.description.trim()) {
      validationErrors.description =
        "Description is required.";
    } else if (formData.description.trim().length < 5) {
      validationErrors.description =
        "Description must contain at least 5 characters.";
    }

    return validationErrors;
  }

  function validateForm() {
    const validationErrors = validateTicketText();

    if (!formData.categoryId) {
      validationErrors.categoryId =
        "Please select a category.";
    }

    if (!formData.priorityId) {
      validationErrors.priorityId =
        "Please select a priority.";
    }

    return validationErrors;
  }

  async function analyzeTicket() {
    const validationErrors = validateTicketText();

    if (Object.keys(validationErrors).length > 0) {
      setErrors((current) => ({
        ...current,
        ...validationErrors,
      }));
      setServerMessage(
        "Enter a valid subject and description before analyzing."
      );
      return false;
    }

    try {
      setCheckingClassification(true);
      setServerMessage("");

      const response = await previewTicketClassification(
        formData.subject,
        formData.description,
        token
      );

      const suggestion = response?.data;

      if (
        !suggestion?.success ||
        !suggestion?.category?.id ||
        !suggestion?.priority?.id
      ) {
        throw new Error(
          "The classifier did not return a valid category and priority."
        );
      }

      setClassificationSuggestion(suggestion);
      setClassificationChecked(true);
      setFormData((current) => ({
        ...current,
        categoryId: String(suggestion.category.id),
        priorityId: String(suggestion.priority.id),
      }));
      setErrors((current) => ({
        ...current,
        categoryId: "",
        priorityId: "",
        subject: "",
        description: "",
      }));

      return true;
    } catch (error) {
      setClassificationChecked(false);
      setClassificationSuggestion(null);
      setServerMessage(
        error.message || "Unable to analyze this ticket."
      );
      return false;
    } finally {
      setCheckingClassification(false);
    }
  }

  async function checkForDuplicates() {
    if (!classificationChecked) {
      setServerMessage(
        "Analyze the ticket before checking for similar tickets."
      );
      return false;
    }

    const validationErrors = validateForm();

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      setServerMessage(
        "Complete the required ticket information before checking."
      );
      return false;
    }

    try {
      setCheckingDuplicates(true);
      setServerMessage("");

      const response = await checkTicketDuplicates(
        formData.subject,
        formData.description,
        token
      );

      const matches = Array.isArray(
        response?.data?.matches
      )
        ? response.data.matches
        : [];

      setDuplicateMatches(matches);
      setDuplicateChecked(true);
      setAllowDuplicateCreation(false);

      return true;
    } catch (error) {
      setDuplicateChecked(false);
      setServerMessage(
        error.message ||
          "Unable to check for similar tickets."
      );

      return false;
    } finally {
      setCheckingDuplicates(false);
    }
  }

  async function handleSubmit(event) {
    event.preventDefault();

    const validationErrors = validateForm();

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }

    if (!duplicateChecked) {
      await checkForDuplicates();
      return;
    }

    if (
      duplicateMatches.length > 0 &&
      !allowDuplicateCreation
    ) {
      setServerMessage(
        "Review the related tickets and confirm that this is a different issue before creating it."
      );
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

              <button
                type="button"
                style={styles.analyzeButton}
                disabled={
                  checkingClassification ||
                  checkingDuplicates ||
                  isSubmitting
                }
                onClick={analyzeTicket}
              >
                {checkingClassification
                  ? "Analyzing..."
                  : classificationChecked
                    ? "Analyze Again"
                    : "Analyze Ticket"}
              </button>

              {classificationSuggestion?.success && (
                <section style={styles.classificationBox}>
                  <h2 style={styles.duplicateTitle}>
                    Automated classification
                  </h2>

                  <div style={styles.classificationGrid}>
                    <div style={styles.classificationValue}>
                      <span style={styles.valueLabel}>
                        Category
                      </span>
                      <strong>
                        {classificationSuggestion.category.name}
                      </strong>
                    </div>

                    <div style={styles.classificationValue}>
                      <span style={styles.valueLabel}>
                        Priority
                      </span>
                      <strong>
                        {classificationSuggestion.priority.name}
                      </strong>
                    </div>

                    <div style={styles.classificationValue}>
                      <span style={styles.valueLabel}>
                        Confidence
                      </span>
                      <strong>
                        {Math.round(
                          Number(
                            classificationSuggestion.confidence
                          ) * 100
                        )}
                        %
                      </strong>
                    </div>
                  </div>

                  <p style={styles.classificationReason}>
                    {classificationSuggestion.reason}
                  </p>

                  {classificationSuggestion.matchedIndicators
                    ?.category?.length > 0 && (
                    <div style={styles.indicatorList}>
                      <span>Detected:</span>
                      {classificationSuggestion.matchedIndicators.category.map(
                        (indicator) => (
                          <small
                            key={indicator}
                            style={styles.indicator}
                          >
                            {indicator}
                          </small>
                        )
                      )}
                    </div>
                  )}

                  <p style={styles.reviewHint}>
                    Category and priority were filled automatically.
                    You may correct either dropdown before continuing.
                  </p>
                </section>
              )}

              {duplicateChecked && (
                <section style={styles.duplicateSection}>
                  <h2 style={styles.duplicateTitle}>
                    Related ticket history
                  </h2>

                  {duplicateMatches.length === 0 ? (
                    <p style={styles.clearMessage}>
                      No similar tickets were found. You can
                      create this ticket.
                    </p>
                  ) : (
                    <>
                      <p style={styles.duplicateIntro}>
                        Similar issues were found. Review their
                        status and previous solutions before
                        creating another ticket.
                      </p>

                      <div style={styles.duplicateList}>
                        {duplicateMatches.map((match) => {
                          const previousTicket = match.ticket;

                          return (
                            <article
                              key={previousTicket.id}
                              style={styles.duplicateCard}
                            >
                              <div
                                style={
                                  styles.duplicateCardHeading
                                }
                              >
                                <strong>
                                  {previousTicket.ticketNumber}
                                </strong>
                                <span style={styles.scoreBadge}>
                                  {match.percentage}% similar
                                </span>
                              </div>

                              <h3 style={styles.matchSubject}>
                                {previousTicket.subject}
                              </h3>

                              <p style={styles.matchDescription}>
                                {previousTicket.description}
                              </p>

                              <div style={styles.matchFacts}>
                                <span>
                                  Status: {previousTicket.status
                                    ?.statusName || "—"}
                                </span>
                                <span>
                                  Category: {previousTicket
                                    .category?.categoryName || "—"}
                                </span>
                              </div>

                              {match.wasResolved &&
                                previousTicket.resolutionNote && (
                                  <div
                                    style={styles.solutionBox}
                                  >
                                    <strong>
                                      Previous solution
                                    </strong>
                                    <p>
                                      {
                                        previousTicket.resolutionNote
                                      }
                                    </p>
                                  </div>
                                )}
                            </article>
                          );
                        })}
                      </div>

                      <label style={styles.confirmationLabel}>
                        <input
                          type="checkbox"
                          checked={allowDuplicateCreation}
                          onChange={(event) =>
                            setAllowDuplicateCreation(
                              event.target.checked
                            )
                          }
                        />
                        This is a different issue. Create a new
                        ticket anyway.
                      </label>
                    </>
                  )}
                </section>
              )}

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
                  type="button"
                  style={styles.checkButton}
                  onClick={checkForDuplicates}
                  disabled={
                    checkingDuplicates ||
                    checkingClassification ||
                    isSubmitting ||
                    !classificationChecked
                  }
                >
                  {checkingDuplicates
                    ? "Checking..."
                    : duplicateChecked
                      ? "Check Again"
                      : "Check Similar Tickets"}
                </button>

                <button
                  type="submit"
                  style={styles.submitButton}
                  disabled={
                    isSubmitting ||
                    checkingDuplicates ||
                    checkingClassification ||
                    !classificationChecked ||
                    !duplicateChecked ||
                    (duplicateMatches.length > 0 &&
                      !allowDuplicateCreation)
                  }
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

  checkButton: {
    padding: "11px 18px",
    border: "1px solid #2563eb",
    borderRadius: "8px",
    backgroundColor: "#eff6ff",
    color: "#1d4ed8",
    fontWeight: 700,
    cursor: "pointer",
  },

  analyzeButton: {
    alignSelf: "flex-start",
    padding: "11px 18px",
    border: "none",
    borderRadius: "8px",
    backgroundColor: "#7c3aed",
    color: "#ffffff",
    fontWeight: 700,
    cursor: "pointer",
  },

  classificationBox: {
    padding: "20px",
    border: "1px solid #c4b5fd",
    borderRadius: "12px",
    backgroundColor: "#f5f3ff",
  },

  classificationGrid: {
    display: "grid",
    gridTemplateColumns: "repeat(3, 1fr)",
    gap: "12px",
    marginTop: "14px",
  },

  classificationValue: {
    padding: "12px",
    borderRadius: "8px",
    backgroundColor: "#ffffff",
  },

  valueLabel: {
    display: "block",
    marginBottom: "5px",
    color: "#667085",
    fontSize: "12px",
  },

  classificationReason: {
    margin: "14px 0",
    color: "#475467",
    lineHeight: 1.5,
  },

  indicatorList: {
    display: "flex",
    alignItems: "center",
    flexWrap: "wrap",
    gap: "7px",
  },

  indicator: {
    padding: "4px 8px",
    borderRadius: "999px",
    backgroundColor: "#ddd6fe",
    color: "#5b21b6",
  },

  reviewHint: {
    margin: "14px 0 0",
    color: "#5b21b6",
    fontSize: "13px",
  },

  duplicateSection: {
    padding: "20px",
    border: "1px solid #dbe3ef",
    borderRadius: "12px",
    backgroundColor: "#f8fafc",
  },

  duplicateTitle: {
    margin: "0 0 8px",
    color: "#172033",
    fontSize: "20px",
  },

  duplicateIntro: {
    margin: "0 0 16px",
    color: "#667085",
    lineHeight: 1.5,
  },

  clearMessage: {
    margin: 0,
    padding: "12px",
    borderRadius: "8px",
    backgroundColor: "#ecfdf3",
    color: "#067647",
  },

  duplicateList: {
    display: "flex",
    flexDirection: "column",
    gap: "12px",
  },

  duplicateCard: {
    padding: "16px",
    border: "1px solid #e4e7ec",
    borderRadius: "10px",
    backgroundColor: "#ffffff",
  },

  duplicateCardHeading: {
    display: "flex",
    alignItems: "center",
    justifyContent: "space-between",
    gap: "12px",
    color: "#2563eb",
  },

  scoreBadge: {
    padding: "5px 9px",
    borderRadius: "999px",
    backgroundColor: "#fef3c7",
    color: "#92400e",
    fontSize: "12px",
    fontWeight: 700,
  },

  matchSubject: {
    margin: "12px 0 6px",
    color: "#172033",
    fontSize: "16px",
  },

  matchDescription: {
    margin: "0 0 12px",
    color: "#475467",
    lineHeight: 1.5,
    whiteSpace: "pre-wrap",
  },

  matchFacts: {
    display: "flex",
    flexWrap: "wrap",
    gap: "12px",
    color: "#667085",
    fontSize: "13px",
  },

  solutionBox: {
    marginTop: "14px",
    padding: "12px",
    borderLeft: "4px solid #16a34a",
    borderRadius: "6px",
    backgroundColor: "#f0fdf4",
    color: "#166534",
  },

  confirmationLabel: {
    display: "flex",
    alignItems: "flex-start",
    gap: "9px",
    marginTop: "16px",
    color: "#344054",
    fontWeight: 700,
    cursor: "pointer",
  },
};

export default CreateTicketPage;