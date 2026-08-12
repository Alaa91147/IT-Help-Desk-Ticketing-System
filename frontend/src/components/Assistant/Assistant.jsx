import { useState } from "react";

import {
  aiTriage,
  applyAiTriage,
} from "../../api/ticketApi";

import { useAuth } from "../../context/AuthContext";

function percentage(value) {
  return `${Math.round(
    (Number(value) || 0) * 100
  )}%`;
}

export default function Assistant({
  ticketId,
  onApplied,
}) {
  const { token, user } = useAuth();

  const role =
    user?.role?.roleName ||
    user?.roleName ||
    user?.role ||
    "";

  const [suggestion, setSuggestion] =
    useState(null);

  const [loading, setLoading] =
    useState(false);

  const [applying, setApplying] =
    useState(false);

  const [error, setError] =
    useState("");

  const [successMessage, setSuccessMessage] =
    useState("");

  const canApply =
    ["Admin", "Manager"].includes(role) &&
    suggestion?.canApply;

  async function runTriage() {
    try {
      setLoading(true);
      setError("");
      setSuccessMessage("");

      const response = await aiTriage(
        ticketId,
        token
      );

      setSuggestion(response?.data || null);
    } catch (requestError) {
      setError(
        requestError.message ||
          "Classification failed."
      );
    } finally {
      setLoading(false);
    }
  }

  async function applySuggestion() {
    const confirmed = window.confirm(
      "Apply this category and priority suggestion?"
    );

    if (!confirmed) return;

    try {
      setApplying(true);
      setError("");
      setSuccessMessage("");

      const response = await applyAiTriage(
        ticketId,
        token
      );

      setSuccessMessage(
        "Classification suggestion applied."
      );

      if (onApplied) {
        await onApplied(response?.data);
      }
    } catch (requestError) {
      setError(
        requestError.message ||
          "Unable to apply the suggestion."
      );
    } finally {
      setApplying(false);
    }
  }

  return (
    <section className="assistant-classifier">
      <button
        type="button"
        className="secondary-button"
        onClick={runTriage}
        disabled={loading || applying}
      >
        {loading
          ? "Analyzing..."
          : "Suggest Classification"}
      </button>

      {error && (
        <div className="assistant-error">
          {error}
        </div>
      )}

      {successMessage && (
        <div className="assistant-success">
          {successMessage}
        </div>
      )}

      {suggestion?.success && (
        <div className="assistant-suggestion">
          <div className="assistant-suggestion-heading">
            <div>
              <span>Suggested category</span>
              <strong>
                {suggestion.category?.name}
              </strong>
            </div>

            <div>
              <span>Suggested priority</span>
              <strong>
                {suggestion.priority?.name}
              </strong>
            </div>

            <div>
              <span>Confidence</span>
              <strong>
                {percentage(
                  suggestion.confidence
                )}
              </strong>
            </div>
          </div>

          <p>{suggestion.reason}</p>

          {suggestion.matchedIndicators?.category
            ?.length > 0 && (
            <div className="assistant-indicators">
              <span>Category indicators:</span>

              {suggestion.matchedIndicators
                .category.map((indicator) => (
                  <small key={indicator}>
                    {indicator}
                  </small>
                ))}
            </div>
          )}

          {suggestion.matchedIndicators?.priority
            ?.length > 0 && (
            <div className="assistant-indicators">
              <span>Priority indicators:</span>

              {suggestion.matchedIndicators
                .priority.map((indicator) => (
                  <small key={indicator}>
                    {indicator}
                  </small>
                ))}
            </div>
          )}

          {canApply && (
            <button
              type="button"
              className="primary-button"
              onClick={applySuggestion}
              disabled={applying}
            >
              {applying
                ? "Applying..."
                : "Apply Suggestion"}
            </button>
          )}
        </div>
      )}
    </section>
  );
}