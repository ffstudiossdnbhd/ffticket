document.addEventListener("DOMContentLoaded", () => {
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
