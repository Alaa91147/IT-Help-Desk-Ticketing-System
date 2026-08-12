import { useState } from "react";

import {
  exportTicketsExcel,
  exportTicketsPdf,
} from "../../api/ticketApi";

import { useAuth } from "../../context/AuthContext";

export default function ExportButtons({ filters = {} }) {
  const { token } = useAuth();
  const [downloading, setDownloading] = useState("");

  async function download(format) {
    try {
      setDownloading(format);

      if (format === "pdf") {
        await exportTicketsPdf(token, filters);
      } else {
        await exportTicketsExcel(token, filters);
      }
    } catch (error) {
      alert(error.message || "Export failed");
    } finally {
      setDownloading("");
    }
  }

  return (
    <div className="export-buttons">
      <button
        type="button"
        className="btn"
        disabled={Boolean(downloading)}
        onClick={() => download("pdf")}
      >
        {downloading === "pdf"
          ? "Preparing PDF…"
          : "Export PDF"}
      </button>

      <button
        type="button"
        className="btn"
        disabled={Boolean(downloading)}
        onClick={() => download("excel")}
      >
        {downloading === "excel"
          ? "Preparing Excel…"
          : "Export Excel"}
      </button>
    </div>
  );
}