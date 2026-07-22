const form = document.getElementById("notificationForm");
const tableBody = document.getElementById("notificationsTable");
const emailInput = document.getElementById("email");
const messageInput = document.getElementById("message");
const clearButton = document.getElementById("clearButton");
const sendButton = document.getElementById("sendButton");

loadNotifications().catch(error => console.error("Erro ao carregar notificações:", error));

form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (messageInput.value.length <= 5) {
        alert("A mensagem deve ter mais de 5 caracteres!");
        return;
    }

    try {
        const response = await fetch("api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                email: emailInput.value,
                message: messageInput.value
            }),
        });

        const result = await response.json();

        if (result.success) {
            form.reset();
            await loadNotifications();
        } else {
            showError(result.error || "Falha ao enviar notificação");
        }
    } catch (error) {
        console.error("Error:", error);
        showError("Erro de rede ao contatar o servidor");
    }
});

async function loadNotifications() {
    try {
        const response = await fetch("api.php");
        const data = await response.json();
        handleMountNotificationTable(data);
        displayClearButton(data);
    } catch (error) {
        console.error("Error:", error);
    }
}

function handleMountNotificationTable(notifications) {
    if (!Array.isArray(notifications)) {
        showError(notifications?.error || "Resposta inválida do servidor!");
        return;
    }

    if (notifications.length === 0) {
        showEmptyMessage();
        return;
    }

    tableBody.innerHTML = notifications.map((notification) => {
        const { id, email, message, status } = notification;
        return `<tr>
                   <td>${escapeHtml(id)}</td>
                   <td>${escapeHtml(email)}</td>
                   <td>${escapeHtml(message)}</td>
                   <td class="status status_${status}">
                     ${escapeHtml(status)}
                   </td>
               </tr>`;
    }).join("");
}

function showError(message) {
    tableBody.innerHTML = `<tr><td class="error" colspan="4">⚠ ${escapeHtml(message)}</td></tr>`;
}

function showEmptyMessage() {
    tableBody.innerHTML = `<tr><td class="empty" colspan="4">Não há mensagens :(</td></tr>`;
}

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (replaceBy) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;",
        '"': "&quot;", "'": "&#39;"
    }[replaceBy]));
}

clearButton.addEventListener("click", async () => {
    if (!confirm("Tem certeza que deseja limpar todas as notificações?")) {
        return;
    }

    try {
        const response = await fetch("api.php", {
            method: "DELETE",
        });
        const result = await response.json();

        if (result.success) {
            await loadNotifications();
        } else {
            showError(result.error || "Falha ao limpar notificações!");
        }
    } catch (error) {
        console.error("Error:", error);
        showError("Erro de rede ao contatar o servidor!");
    }
});

function displayClearButton(notifications) {
    if (notifications.length > 0) {
        clearButton.style.display = "block";
    } else {
        clearButton.style.display = "none";
    }
}