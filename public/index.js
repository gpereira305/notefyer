const form = document.getElementById("notificationForm");
const tableBody = document.getElementById("notificationsTable");
const emailInput = document.getElementById("email");
const messageInput = document.getElementById("message");
const clearButton = document.getElementById("clearButton");
const sendButton = document.getElementById("sendButton");

const API_URL = "api.php";

handleFetchAllNotifications().catch(error => console.error("Erro ao carregar notificações:", error));

messageInput.addEventListener('input', (event) => {
    const MAX_CHARS = 50;
    const value = event.target.value;

    if (value.length > MAX_CHARS) {
        event.target.value = value.slice(0, MAX_CHARS);
    }
});

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    standbyForProcessing('processing');

    if (messageInput.value.length <= 5) {
        alert("A mensagem deve ter mais de 5 caracteres!");
        standbyForProcessing();
        return;
    }

    try {
        await handlePostNotification();
    } catch (error) {
        console.error("Error:", error);
        showError("Erro de rede ao contatar o servidor");
    } finally {
        standbyForProcessing();
    }
});

async function handlePostNotification() {
    const response = await fetch(API_URL, {
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
        await handleFetchAllNotifications();
    } else {
        showError(result.error || "Falha ao enviar notificação");
    }
}

async function handleFetchAllNotifications() {
    try {
        const response = await fetch(API_URL);
        const data = await response.json();
        handleMountNotificationTable(data);
        displayClearButton(data);
    } catch (error) {
        console.error("Error:", error);
        showError("Erro ao carregar notificações!");
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
        return `
            <tr>
               <td>${escapeHtml(id)}</td>
               <td>${escapeHtml(email)}</td>
               <td>${escapeHtml(message)}</td>
               <td class="status status_${status}">
                 ${escapeHtml(status)}
               </td>
            </tr>
        `;
    }).join("");
}

function showError(message) {
    tableBody.innerHTML = `
        <tr>
            <td class="error" colspan="4">
                ⚠ ${escapeHtml(message)}
            </td>
        </tr>
    `;
}

function showEmptyMessage() {
    tableBody.innerHTML = `
        <tr>
            <td class="empty" colspan="4">
                Não há mensagens para exibir :(
            </td>
        </tr>
    `;
}

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (replaceBy) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;",
        '"': "&quot;", "'": "&#39;"
    }[replaceBy]));
}

function standbyForProcessing(currentState) {
    const isProcessing = currentState === 'processing';

    sendButton.disabled = isProcessing;
    clearButton.disabled = isProcessing;
    sendButton.classList.toggle('processing', isProcessing);
    sendButton.textContent = isProcessing ? 'Enviando mensagem...' : 'Enviar via AJAX';
}

clearButton.addEventListener("click", async () => {
    if (!confirm("Tem certeza que deseja limpar todas as notificações?")) {
        return;
    }

    try {
        clearButton.disabled = true;
        clearButton.classList.add('processing-clear');
        clearButton.textContent = 'Limpando histórico...';
        await handleDeleteAllNotifications();
    } catch (error) {
        console.error("Error:", error);
        showError("Erro de rede ao contatar o servidor!");
    } finally {
        clearButton.disabled = false;
        clearButton.classList.remove('processing-clear');
        clearButton.textContent = 'Limpar histórico';
    }
});

async function handleDeleteAllNotifications() {
    const response = await fetch(API_URL, {
        method: "DELETE",
    });
    const result = await response.json();

    if (result.success) {
        await handleFetchAllNotifications();
    } else {
        showError(result.error || "Falha ao remover notificações!");
    }
}

function displayClearButton(notifications) {
    if (notifications.length === 0) {
        clearButton.style.display = "none";
    } else {
        clearButton.style.display = "block";
    }
}