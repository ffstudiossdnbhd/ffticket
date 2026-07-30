document.addEventListener("DOMContentLoaded", () => {
    const appBody = document.body;
    const csrfToken = appBody.dataset.csrfToken ?? "";
    const presenceRoot = document.querySelector("[data-ticket-presence]");
    const activityUrl = appBody.dataset.activityUrl;
    let editingUntil = 0;

    const getClientId = () => {
        const key = "ffticket.activity-client-id";
        let value = sessionStorage.getItem(key);
        if (!value) {
            value = typeof globalThis.crypto?.randomUUID === "function"
                ? globalThis.crypto.randomUUID()
                : `web-${Date.now()}-${Math.random().toString(36).slice(2)}`;
            sessionStorage.setItem(key, value);
        }
        return value;
    };

    const showTimeoutWarning = (timeout, locked = false) => {
        if (!timeout) {
            return;
        }
        let banner = document.querySelector("[data-timeout-warning]");
        if (!banner) {
            banner = document.createElement("div");
            banner.className = "alert alert-error timeout-warning";
            banner.dataset.timeoutWarning = "true";
            document.querySelector(".workspace")?.prepend(banner);
        }
        banner.textContent = locked
            ? `Your timeout is active until ${timeout.release_at_myt} MYT. You have been signed out.`
            : `Your account will be timed out at ${timeout.effective_at_myt} MYT and released at ${timeout.release_at_myt} MYT.`;
    };

    const updateCollaborators = (collaborators) => {
        const copy = presenceRoot?.querySelector("[data-collaboration-copy]");
        if (!copy) {
            return;
        }
        if (!Array.isArray(collaborators) || collaborators.length === 0) {
            copy.textContent = "No other team member is viewing this ticket.";
            return;
        }
        copy.textContent = collaborators.map((person) => `${person.name} (${person.mode})`).join(", ");
    };

    const heartbeat = async () => {
        if (!activityUrl || !csrfToken) {
            return;
        }
        const ticketId = presenceRoot?.dataset.ticketId ?? "0";
        const mode = Number(ticketId) > 0 && Date.now() < editingUntil ? "editing" : "viewing";
        const body = new URLSearchParams({
            _csrf: csrfToken,
            client_id: getClientId(),
            ticket_id: ticketId,
            mode,
        });
        try {
            const response = await fetch(activityUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body: body.toString(),
            });
            const result = await response.json();
            if (response.status === 423) {
                showTimeoutWarning(result.data, true);
                window.setTimeout(() => window.location.assign(appBody.dataset.loginUrl ?? "/login"), 700);
                return;
            }
            if (!response.ok) {
                return;
            }
            updateCollaborators(result.data?.collaborators);
            showTimeoutWarning(result.data?.timeout);
        } catch {
            // A temporary network failure must not disrupt ticket work.
        }
    };

    document.querySelectorAll("[data-ticket-edit-control]").forEach((control) => {
        control.addEventListener("focus", () => { editingUntil = Date.now() + 16000; });
        control.addEventListener("input", () => { editingUntil = Date.now() + 16000; });
        control.addEventListener("change", () => { editingUntil = Date.now() + 16000; });
    });

    heartbeat();
    window.setInterval(heartbeat, presenceRoot ? 15000 : 30000);

    const faqDialog = document.querySelector("[data-faq-dialog]");
    const faqList = document.querySelector("[data-faq-list]");
    const closeFaqDialog = () => {
        if (faqDialog) {
            faqDialog.hidden = true;
        }
    };
    document.querySelectorAll("[data-faq-close]").forEach((button) => button.addEventListener("click", closeFaqDialog));
    document.querySelectorAll("[data-faq-open]").forEach((button) => button.addEventListener("click", async () => {
        if (!faqDialog || !faqList) {
            return;
        }
        faqDialog.hidden = false;
        faqList.replaceChildren();
        const loading = document.createElement("p");
        loading.className = "empty";
        loading.textContent = "Loading FAQs…";
        faqList.append(loading);
        try {
            const response = await fetch(appBody.dataset.faqUrl ?? "", { credentials: "same-origin" });
            const result = await response.json();
            if (!response.ok || !Array.isArray(result.data)) {
                throw new Error("Unable to load FAQs.");
            }
            faqList.replaceChildren();
            if (result.data.length === 0) {
                const empty = document.createElement("p");
                empty.className = "empty";
                empty.textContent = "No FAQs have been published yet.";
                faqList.append(empty);
            }
            result.data.forEach((faq) => {
                const item = document.createElement("article");
                item.className = "faq-item";
                const title = document.createElement("h3");
                title.textContent = faq.title ?? "";
                const description = document.createElement("p");
                description.textContent = faq.description ?? "";
                item.append(title, description);
                faqList.append(item);
            });
        } catch {
            faqList.replaceChildren();
            const error = document.createElement("p");
            error.className = "empty";
            error.textContent = "Unable to load FAQs right now.";
            faqList.append(error);
        }
    }));

    document.querySelectorAll("[data-confirm-delete]").forEach((form) => form.addEventListener("submit", (event) => {
        if (!window.confirm("Delete this FAQ permanently?")) {
            event.preventDefault();
        }
    }));
    document.querySelectorAll("[data-confirm-release]").forEach((form) => form.addEventListener("submit", (event) => {
        if (!window.confirm("Release this user from timeout now?")) {
            event.preventDefault();
        }
    }));

    const navToggle = document.querySelector(".nav-toggle");
    const sidebar = document.querySelector(".sidebar");

    if (navToggle && sidebar) {
        navToggle.addEventListener("click", () => {
            const open = sidebar.classList.toggle("is-open");
            navToggle.setAttribute("aria-expanded", String(open));
        });
    }

    document.querySelectorAll(".ticket-row[data-href]").forEach((row) => {
        row.addEventListener("dblclick", (event) => {
            if (event.target.closest("a, button, input, select, textarea")) {
                return;
            }
            window.location.assign(row.dataset.href);
        });
    });

    const ticketUpdateForm = document.querySelector("[data-ticket-update-form]");
    const ticketIdInput = ticketUpdateForm?.querySelector('input[name="id"]');
    const statusSelect = ticketUpdateForm?.querySelector('select[name="status"]');
    const urgencySelect = ticketUpdateForm?.querySelector('select[name="urgency_type_id"]');
    const assigneeSelect = ticketUpdateForm?.querySelector('select[name="assigned_to"]');

    if (ticketUpdateForm && ticketIdInput) {
        const setSelectValue = (select, value, fallback) => {
            if (!select) {
                return;
            }

            const hasOption = [...select.options].some((option) => option.value === value);
            select.value = hasOption ? value : fallback;
        };

        document.querySelectorAll("[data-select-ticket]").forEach((button) => {
            button.addEventListener("click", () => {
                const ticketId = button.dataset.ticketId ?? "";

                if (!/^[1-9]\d*$/.test(ticketId)) {
                    return;
                }

                ticketIdInput.value = ticketId;
                setSelectValue(statusSelect, button.dataset.ticketStatus ?? "", "");
                setSelectValue(urgencySelect, button.dataset.ticketUrgencyTypeId ?? "", "");

                const assignedTo = button.dataset.ticketAssignedTo ?? "";
                setSelectValue(assigneeSelect, assignedTo === "0" ? "" : assignedTo, "no_change");
                ticketUpdateForm.scrollIntoView({ behavior: "smooth", block: "center" });
                ticketIdInput.focus({ preventScroll: true });
            });
        });
    }

    const dateFilterForm = document.querySelector("[data-date-filter-form]");
    if (dateFilterForm) {
        const from = dateFilterForm.querySelector('input[name="from"]');
        const to = dateFilterForm.querySelector('input[name="to"]');
        let filterTimer = 0;

        const applyDateFilter = () => {
            if (!from || !to || !from.value || !to.value) {
                return;
            }

            const valid = from.value <= to.value;
            from.setCustomValidity(valid ? "" : "Created From must be on or before Created To.");
            to.setCustomValidity(valid ? "" : "Created To must be on or after Created From.");
            from.toggleAttribute("aria-invalid", !valid);
            to.toggleAttribute("aria-invalid", !valid);

            if (!valid) {
                from.reportValidity();
                return;
            }

            window.clearTimeout(filterTimer);
            filterTimer = window.setTimeout(() => dateFilterForm.requestSubmit(), 250);
        };

        dateFilterForm.querySelectorAll("[data-auto-filter]").forEach((input) => {
            input.addEventListener("change", applyDateFilter);
        });
    }

    const columns = [...document.querySelectorAll("[data-kanban-status]")];
    const cards = [...document.querySelectorAll(".ticket-card[draggable='true']")];
    const moveForm = document.querySelector("[data-kanban-move-form]");

    if (columns.length && cards.length && moveForm) {
        let draggedCard = null;

        cards.forEach((card) => {
            card.addEventListener("dragstart", () => {
                draggedCard = card;
                card.classList.add("is-dragging");
            });

            card.addEventListener("dragend", () => {
                card.classList.remove("is-dragging");
                columns.forEach((column) => column.classList.remove("drag-over"));
                draggedCard = null;
            });
        });

        columns.forEach((column) => {
            column.addEventListener("dragover", (event) => {
                event.preventDefault();
                if (draggedCard && draggedCard.dataset.ticketStatus !== column.dataset.kanbanStatus) {
                    column.classList.add("drag-over");
                }
            });

            column.addEventListener("dragleave", () => {
                column.classList.remove("drag-over");
            });

            column.addEventListener("drop", async (event) => {
                event.preventDefault();
                column.classList.remove("drag-over");

                if (!draggedCard || draggedCard.dataset.ticketStatus === column.dataset.kanbanStatus) {
                    return;
                }

                const droppedCard = draggedCard;
                const csrf = moveForm.querySelector('input[name="_csrf"]');
                const destination = column.querySelector(".kanban-list");
                const previousStatus = droppedCard.dataset.ticketStatus;
                const payload = new URLSearchParams({
                    _csrf: csrf?.value ?? "",
                    id: droppedCard.dataset.ticketId ?? "",
                    status: column.dataset.kanbanStatus ?? "",
                });

                destination?.querySelector(".empty")?.remove();
                destination?.append(droppedCard);
                droppedCard.dataset.ticketStatus = column.dataset.kanbanStatus ?? "";

                try {
                    const response = await fetch(moveForm.action, {
                        method: "POST",
                        credentials: "same-origin",
                        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                        body: payload.toString(),
                    });

                    if (!response.ok) {
                        throw new Error("The ticket could not be moved.");
                    }

                    window.location.reload();
                } catch (error) {
                    droppedCard.dataset.ticketStatus = previousStatus;
                    window.location.reload();
                }
            });
        });
    }
});
