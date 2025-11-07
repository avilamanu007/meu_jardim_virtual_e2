<?php
// views/calendar/index.php
?>

<div class="max-w-7xl mx-auto">
    <!-- Cabeçalho -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-green-700 mb-2">📅 Calendário de Cuidados</h1>
        <p class="text-gray-600">Acompanhe todos os cuidados das suas plantas em um só lugar</p>
    </div>

    <!-- Legenda -->
    <div class="mb-6 p-4 bg-white rounded-lg shadow-sm border">
        <h3 class="font-semibold text-gray-700 mb-3">Legenda:</h3>
        <div class="flex flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-600">Cuidados Realizados</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-600">Próximos Cuidados</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-600">Cuidados Atrasados</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-cyan-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-600">Regas Programadas</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-orange-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-600">Regas Atrasadas</span>
            </div>
        </div>
    </div>

    <!-- Calendário Simples -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-6">
            <h2 id="calendar-month" class="text-xl font-semibold text-gray-800"></h2>

            <div class="flex gap-2">
                <button onclick="changeMonth(-1)" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    ← Mês Anterior
                </button>
                <button onclick="changeMonth(1)" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Próximo Mês →
                </button>
            </div>
        </div>

        <!-- Grid do Calendário -->
        <div class="grid grid-cols-7 gap-2 mb-2">
            <?php
            $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            foreach ($diasSemana as $dia): ?>
                <div class="text-center font-semibold text-gray-600 py-2 text-sm">
                    <?= $dia ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="calendar-grid" class="grid grid-cols-7 gap-2">
            <!-- O calendário será preenchido via JavaScript -->
        </div>
    </div>

    <!-- Lista de Eventos do Mês -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Eventos do Mês</h3>
        <div id="month-events" class="space-y-3">
            <!-- Eventos serão carregados aqui -->
        </div>
    </div>
</div>

<!-- Modal para Detalhes do Evento -->
<div id="event-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modal-title" class="text-xl font-semibold"></h3>
            <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <div id="modal-content" class="space-y-3">
            <!-- Conteúdo dinâmico -->
        </div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                Fechar
            </button>
        </div>
    </div>
</div>

<script>
// Dados dos eventos (convertidos do PHP para JavaScript)
const calendarEvents = <?= json_encode($calendarEvents) ?>;
const currentDate = new Date();

function updateMonthTitle(year, month) {
    const monthName = new Date(year, month).toLocaleString('pt-BR', { month: 'long' });
    const titleElement = document.getElementById('calendar-month');
    titleElement.textContent = `${monthName.charAt(0).toUpperCase() + monthName.slice(1)} ${year}`;
}


function generateCalendar(year = currentDate.getFullYear(), month = currentDate.getMonth()) {
    updateMonthTitle(year, month);
    const calendarGrid = document.getElementById('calendar-grid');
    const monthEvents = document.getElementById('month-events');
    
    // Limpar conteúdo anterior
    calendarGrid.innerHTML = '';
    monthEvents.innerHTML = '';
    
    // Primeiro dia do mês
    const firstDay = new Date(year, month, 1);
    // Último dia do mês
    const lastDay = new Date(year, month + 1, 0);
    
    // Dias do mês anterior (para preencher a primeira semana)
    const startingDay = firstDay.getDay(); // 0 = Domingo, 1 = Segunda, etc.
    
    // Dias do mês anterior
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    
    // Preencher dias do mês anterior
    for (let i = 0; i < startingDay; i++) {
        const day = prevMonthLastDay - startingDay + i + 1;
        const date = new Date(year, month - 1, day);
        const dateStr = date.toISOString().split('T')[0];
        
        const dayElement = createDayElement(day, dateStr, 'prev-month');
        calendarGrid.appendChild(dayElement);
    }
    
    // Dias do mês atual
    const currentMonthEvents = [];
    
    for (let day = 1; day <= lastDay.getDate(); day++) {
        const date = new Date(year, month, day);
        const dateStr = date.toISOString().split('T')[0];
        
        // Encontrar eventos para este dia
        const dayEvents = calendarEvents.filter(event => event.start === dateStr);
        
        const dayElement = createDayElement(day, dateStr, 'current-month', dayEvents);
        calendarGrid.appendChild(dayElement);
        
        // Adicionar à lista de eventos do mês
        dayEvents.forEach(event => {
            currentMonthEvents.push({
                ...event,
                day: day
            });
        });
    }
    
    // Preencher dias do próximo mês (para completar a última semana)
    const totalCells = 42; // 6 semanas * 7 dias
    const remainingCells = totalCells - calendarGrid.children.length;
    
    for (let day = 1; day <= remainingCells; day++) {
        const date = new Date(year, month + 1, day);
        const dateStr = date.toISOString().split('T')[0];
        
        const dayElement = createDayElement(day, dateStr, 'next-month');
        calendarGrid.appendChild(dayElement);
    }
    
    // Exibir eventos do mês
    displayMonthEvents(currentMonthEvents, month, year);
    generateCalendar();

}

function createDayElement(day, dateStr, monthType, events = []) {
    const dayElement = document.createElement('div');
    dayElement.className = 'min-h-24 p-2 border rounded-lg text-sm relative';
    
    // Estilos baseados no tipo de mês
    if (monthType === 'current-month') {
        dayElement.classList.add('bg-white', 'border-gray-200');
        
        // Destacar o dia atual
        const today = new Date().toISOString().split('T')[0];
        if (dateStr === today) {
            dayElement.classList.add('bg-blue-50', 'border-blue-300');
        }
    } else {
        dayElement.classList.add('bg-gray-50', 'text-gray-400', 'border-gray-100');
    }
    
    // Número do dia
    const dayNumber = document.createElement('div');
    dayNumber.className = 'font-semibold mb-1';
    dayNumber.textContent = day;
    dayElement.appendChild(dayNumber);
    
    // Eventos do dia
    events.forEach(event => {
        const eventElement = document.createElement('div');
        eventElement.className = 'text-xs p-1 mb-1 rounded cursor-pointer hover:opacity-80 truncate';
        eventElement.style.backgroundColor = event.color;
        eventElement.style.color = 'white';
        eventElement.title = event.title;
        eventElement.textContent = event.title.split(' - ')[0]; // Mostrar apenas o ícone e tipo
        
        eventElement.onclick = () => showEventDetails(event);
        
        dayElement.appendChild(eventElement);
    });
    
    return dayElement;
}

function displayMonthEvents(events, month, year) {
    const container = document.getElementById('month-events');
    
    if (events.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <p>Nenhum evento agendado para este mês.</p>
            </div>
        `;
        return;
    }
    
    // Agrupar eventos por dia
    const eventsByDay = {};
    events.forEach(event => {
        if (!eventsByDay[event.day]) {
            eventsByDay[event.day] = [];
        }
        eventsByDay[event.day].push(event);
    });
    
    // Ordenar dias
    const sortedDays = Object.keys(eventsByDay).sort((a, b) => a - b);
    
    // Exibir eventos
    sortedDays.forEach(day => {
        const dayEvents = eventsByDay[day];
        const date = new Date(year, month, parseInt(day));
        
        const dayHeader = document.createElement('div');
        dayHeader.className = 'font-semibold text-gray-700 mb-2';
        dayHeader.textContent = `${day} de ${date.toLocaleDateString('pt-BR', { month: 'long' })}`;
        
        container.appendChild(dayHeader);
        
        dayEvents.forEach(event => {
            const eventElement = document.createElement('div');
            eventElement.className = 'flex items-center p-3 bg-gray-50 rounded-lg mb-2 cursor-pointer hover:bg-gray-100 transition';
            eventElement.onclick = () => showEventDetails(event);
            
            eventElement.innerHTML = `
                <div class="w-3 h-3 rounded-full mr-3" style="background-color: ${event.color}"></div>
                <div class="flex-1">
                    <div class="font-medium">${event.title}</div>
                    <div class="text-sm text-gray-600">${event.plant_name}</div>
                </div>
                <div class="text-xs text-gray-500">
                    ${event.care_type}
                </div>
            `;
            
            container.appendChild(eventElement);
        });
    });
}

function showEventDetails(event) {
    const modal = document.getElementById('event-modal');
    const title = document.getElementById('modal-title');
    const content = document.getElementById('modal-content');
    
    title.textContent = event.title;
    
    let detailsHtml = `
        <div class="space-y-2">
            <div><strong>Planta:</strong> ${event.plant_name}</div>
            <div><strong>Tipo:</strong> ${event.care_type}</div>
            <div><strong>Data:</strong> ${formatDate(event.start)}</div>
    `;
    
    if (event.observations) {
        detailsHtml += `<div><strong>Observações:</strong> ${event.observations}</div>`;
    }
    
    if (event.days_until !== undefined) {
        const status = event.is_overdue ? 
            `<span class="text-red-600 font-semibold">Atrasado há ${Math.abs(event.days_until)} dias</span>` :
            `<span class="text-blue-600">Em ${event.days_until} dias</span>`;
        detailsHtml += `<div><strong>Status:</strong> ${status}</div>`;
    }
    
    detailsHtml += `</div>`;
    
    content.innerHTML = detailsHtml;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('event-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function changeMonth(direction) {
    currentDate.setMonth(currentDate.getMonth() + direction);
    generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
}

// Fechar modal ao clicar fora
document.getElementById('event-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Inicializar calendário
generateCalendar();
</script>

<style>
.min-h-24 {
    min-height: 6rem;
}

#calendar-grid > div {
    transition: all 0.2s ease;
}

#calendar-grid > div:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>