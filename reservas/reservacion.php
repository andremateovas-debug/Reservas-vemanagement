<?php
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reservacion</title>
    <style>
        * {
            margin: 0; padding: 0; box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #ffffff;
            color: #000000;
            min-height: 100vh;
            overflow-x: hidden;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }

        .animate-in { animation: fadeInUp 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }

        .notification {
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%) translateY(-150%);
            z-index: 9999;
            background: #000000;
            color: #ffffff;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 90%;
            min-width: 300px;
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid #333333;
        }

        .notification.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
            animation: slideDown 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .notification span:first-child { font-size: 20px; flex-shrink: 0; }
        .notification span:last-child  { flex: 1; font-size: 14px; line-height: 1.4; }

        .notification.error   { background: #1a1a1a; border-color: #ff4444; }
        .notification.warning { background: #1a1a1a; border-color: #ffaa00; }
        .notification.success { background: #1a1a1a; border-color: #00ff00; }
        .notification.info    { background: #1a1a1a; border-color: #0088ff; }

        .page-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-home {
            color: #000000;
            text-decoration: none;
            font-size: 14px;
            padding: 8px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 20px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
        }

        .back-home:hover { background: #f5f5f5; border-color: #000000; }

        .lang-switcher { display: flex; gap: 8px; align-items: center; }

        .lang-btn {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            color: #666666;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .lang-btn.active { background: #000000; color: #ffffff; border-color: #000000; }

        .reservation-container {
            max-width: 100%;
            width: 100%;
            min-height: 100vh;
            padding: 80px 20px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .reservation-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px 25px;
            width: 100%;
            max-width: 480px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }

        .reservation-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: #000000;
        }

        .restaurant-logo {
            text-align: center;
            margin-bottom: 30px;
            font-size: 32px;
            font-weight: 700;
            color: #000000;
            letter-spacing: 1px;
            position: relative;
        }

        .restaurant-logo::after {
            content: '';
            display: block;
            width: 60px;
            height: 2px;
            background: #000000;
            margin: 12px auto 0;
            border-radius: 2px;
        }

        .step-indicators { display: flex; justify-content: center; gap: 20px; margin-bottom: 30px; }

        .step { display: flex; flex-direction: column; align-items: center; gap: 8px; }

        .step-circle {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: #f5f5f5;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; color: #999999;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }

        .step.active .step-circle {
            background: #000000; color: #ffffff; border-color: #000000;
            box-shadow: 0 0 0 4px rgba(0,0,0,0.1);
        }

        .step-label { font-size: 12px; color: #999999; text-transform: uppercase; letter-spacing: 0.5px; }
        .step.active .step-label { color: #000000; font-weight: 600; }

        .form-group { margin-bottom: 24px; position: relative; }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #000000;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .required-star { color: #ff4444; }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            background: #ffffff;
            color: #000000;
            transition: all 0.3s;
            outline: none;
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus { border-color: #000000; box-shadow: 0 0 0 3px rgba(0,0,0,0.05); }

        .form-textarea { resize: vertical; min-height: 100px; font-family: inherit; }

        .time-slots-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; }

        .time-btn {
            padding: 14px 8px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            background: #ffffff;
            color: #000000;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .time-btn:hover:not(.disabled) { border-color: #000000; background: #f5f5f5; }
        .time-btn.selected { background: #000000; color: #ffffff; border-color: #000000; }
        .time-btn.disabled { opacity: 0.3; cursor: not-allowed; background: #f5f5f5; }

        .time-section { margin-bottom: 20px; }
        .time-section-title { font-size: 13px; font-weight: 600; color: #666666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; padding-left: 4px; }

        .btn {
            width: 100%; padding: 16px; border: none; border-radius: 12px;
            font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            letter-spacing: 0.5px;
        }

        .btn-primary { background: #000000; color: #ffffff; }
        .btn-primary:hover { background: #333333; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }

        .btn-secondary { background: #ffffff; color: #000000; border: 1px solid #e0e0e0; }
        .btn-secondary:hover { background: #f5f5f5; border-color: #000000; }

        .buttons-row { display: flex; gap: 12px; margin-top: 24px; }
        .buttons-row .btn { flex: 1; }

        .hidden { display: none !important; }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0; visibility: hidden;
            transition: all 0.3s;
        }

        .modal-overlay.show { opacity: 1; visibility: visible; }

        .modal-content {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px;
            max-width: 400px;
            width: 100%;
            position: relative;
            transform: scale(0.9);
            transition: transform 0.3s;
            border: 1px solid #e0e0e0;
        }

        .modal-overlay.show .modal-content { transform: scale(1); }

        .modal-icon  { font-size: 48px; text-align: center; margin-bottom: 20px; }
        .modal-title { font-size: 24px; font-weight: 700; text-align: center; margin-bottom: 15px; color: #000000; }
        .modal-text  { color: #666666; text-align: center; line-height: 1.6; margin-bottom: 25px; }

        .modal-close {
            background: #000000; color: #ffffff; border: none;
            padding: 14px 24px; border-radius: 10px; width: 100%;
            font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s;
        }

        .modal-close:hover { background: #333333; }

        .toggle-container {
            display: flex; align-items: center; gap: 12px;
            padding: 16px; background: #f5f5f5; border-radius: 12px;
            margin-bottom: 20px; border: 1px solid #e0e0e0;
            cursor: pointer; user-select: none;
        }

        .toggle-switch { position: relative; width: 50px; height: 28px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }

        .toggle-slider {
            position: absolute;
            cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background: #cccccc; border-radius: 28px; transition: 0.3s;
        }

        .toggle-slider:before {
            position: absolute; content: "";
            height: 20px; width: 20px;
            left: 4px; bottom: 4px;
            background: white; border-radius: 50%; transition: 0.3s;
        }

        input:checked + .toggle-slider { background: #000000; }
        input:checked + .toggle-slider:before { transform: translateX(22px); }

        .toggle-label { flex: 1; font-size: 14px; color: #000000; font-weight: 500; }

        @media (max-width: 480px) {
            .reservation-card { padding: 25px 20px; }
            .time-slots-grid { grid-template-columns: repeat(2, 1fr); }
            .buttons-row { flex-direction: column; }
            .notification { min-width: auto; width: 90%; }
        }

        @media (max-width: 360px) {
            .time-slots-grid { grid-template-columns: 1fr; }
            .step-indicators { gap: 12px; }
            .step-circle { width: 32px; height: 32px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <a href="<?php echo SITE_URL; ?>" class="back-home">
            <span>&#8592;</span>
            <span data-i18n="backHome">Volver al inicio</span>
        </a>
        <div class="lang-switcher">
            <button class="lang-btn active" data-lang="es">ES</button>
            <button class="lang-btn" data-lang="en">EN</button>
        </div>
    </div>

    <div class="notification" id="notification" role="alert" aria-live="polite"></div>

    <div class="reservation-container">
        <div class="reservation-card animate-in">
            <div class="restaurant-logo"><?php echo SITE_NAME; ?></div>

            <div class="step-indicators">
                <div class="step active" data-step="1">
                    <div class="step-circle">1</div>
                    <div class="step-label" data-i18n="stepDate">Fecha</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="step-label" data-i18n="stepContact">Contacto</div>
                </div>
            </div>

            <div id="step1">
                <form id="reservationFormStep1">
                    <div class="form-group">
                        <label class="form-label" for="name">
                            <span data-i18n="name">Nombre</span>
                            <span class="required-star">*</span>
                        </label>
                        <input type="text" id="name" name="name" class="form-input" required
                               data-i18n-placeholder="namePlaceholder"
                               placeholder="Tu nombre completo">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="date">
                            <span data-i18n="date">Fecha</span>
                            <span class="required-star">*</span>
                        </label>
                        <input type="date" id="date" name="date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="guests">
                            <span data-i18n="guests">Numero de personas</span>
                            <span class="required-star">*</span>
                        </label>
                        <select id="guests" name="guests" class="form-select" required>
                            <option value="">Selecciona...</option>
                            <?php for ($i = 1; $i <= 19; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?> persona<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                            <option value="20">+19 personas (evento)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span data-i18n="time">Hora</span>
                            <span class="required-star">*</span>
                        </label>

                        <input type="hidden" id="selectedTime" name="selectedTime">

                        <div class="time-section">
                            <div class="time-section-title" data-i18n="lunchTime">Comida (7:30 AM - 12:30 PM)</div>
                            <div class="time-slots-grid" id="morningSlots"></div>
                        </div>

                        <div class="time-section">
                            <div class="time-section-title" data-i18n="dinnerTime">Cena (5:00 PM - 10:30 PM)</div>
                            <div class="time-slots-grid" id="eveningSlots"></div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" onclick="validateStep1()">
                        <span data-i18n="continue">Continuar</span>
                        <span>&#8594;</span>
                    </button>
                </form>
            </div>

            <div id="step2" class="hidden">
                <form id="reservationFormStep2" action="procesar_reserva.php" method="POST" novalidate>
                    <input type="hidden" id="finalNombre"  name="nombre">
                    <input type="hidden" id="finalFecha"   name="fecha">
                    <input type="hidden" id="finalHora"    name="hora">
                    <input type="hidden" id="finalPersonas"name="personas">
                    <input type="hidden" id="esAlergico"   name="es_alergico" value="0">

                    <div class="form-group">
                        <label class="form-label" for="email">
                            <span data-i18n="email">Email</span>
                        </label>
                        <input type="email" id="email" name="email" class="form-input"
                               data-i18n-placeholder="emailPlaceholder"
                               placeholder="tu@email.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="telefono">
                            <span data-i18n="phone">Telefono</span>
                            <span class="required-star">*</span>
                        </label>
                        <input type="tel" id="telefono" name="telefono" class="form-input" required
                               data-i18n-placeholder="phonePlaceholder"
                               placeholder="+52 123 456 7890">
                    </div>

                    <label class="toggle-container">
                        <span class="toggle-switch">
                            <input type="checkbox" id="allergyToggle">
                            <span class="toggle-slider"></span>
                        </span>
                        <span class="toggle-label" data-i18n="allergyToggle">
                            Tienes alguna alergia alimentaria?
                        </span>
                    </label>

                    <div class="form-group">
                        <label class="form-label" for="comentarios">
                            <span data-i18n="comments">Comentarios</span>
                        </label>
                        <textarea id="comentarios" name="comentarios" class="form-textarea"
                                  data-i18n-placeholder="commentsPlaceholder"
                                  placeholder="Algun detalle especial?"></textarea>
                    </div>

                    <div class="buttons-row">
                        <button type="button" class="btn btn-secondary" onclick="switchStep(2, 1)">
                            <span>&#8592;</span>
                            <span data-i18n="back">Atras</span>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <span data-i18n="confirm">Confirmar Reserva</span>
                            <span>&#10003;</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="capacityModal" role="dialog" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-icon">&#127881;</div>
            <h2 class="modal-title" id="modalTitle" data-i18n="eventTitle">Evento Especial!</h2>
            <p class="modal-text" data-i18n="eventMessage">
                Para reservas de mas de 19 personas, por favor contactanos directamente.
            </p>
            <button class="modal-close" id="closeModal" data-i18n="understood">Entendido</button>
        </div>
    </div>

    <script>
        const translations = {
            es: {
                backHome: 'Volver al inicio',
                stepDate: 'Fecha',
                stepContact: 'Contacto',
                name: 'Nombre',
                namePlaceholder: 'Tu nombre completo',
                date: 'Fecha',
                guests: 'Numero de personas',
                time: 'Hora',
                lunchTime: 'Comida (7:30 AM - 12:30 PM)',
                dinnerTime: 'Cena (5:00 PM - 10:30 PM)',
                continue: 'Continuar',
                email: 'Email',
                emailPlaceholder: 'tu@email.com',
                phone: 'Telefono',
                phonePlaceholder: '+52 123 456 7890',
                allergyToggle: 'Tienes alguna alergia alimentaria?',
                comments: 'Comentarios',
                commentsPlaceholder: 'Algun detalle especial?',
                back: 'Atras',
                confirm: 'Confirmar Reserva',
                eventTitle: 'Evento Especial!',
                eventMessage: 'Para reservas de mas de 19 personas, por favor contactanos directamente.',
                understood: 'Entendido',
                requiredFields: 'Por favor completa todos los campos requeridos',
                selectTime: 'Por favor selecciona una hora',
                allergyRequired: 'Si tienes alergias, por favor especificalas en los comentarios',
                allergyPlaceholder: 'Especifica tus alergias. Ej: Alergia a mani, gluten, mariscos...',
                dateOccupied: 'La fecha {date} a las {time} ya esta reservada. Te sugerimos {nextTime}',
                dateOccupiedNoAlternative: 'La fecha {date} a las {time} ya esta reservada. Por favor elige otra hora u otra fecha.',
            },
            en: {
                backHome: 'Back to home',
                stepDate: 'Date',
                stepContact: 'Contact',
                name: 'Name',
                namePlaceholder: 'Your full name',
                date: 'Date',
                guests: 'Number of guests',
                time: 'Time',
                lunchTime: 'Lunch (7:30 AM - 12:30 PM)',
                dinnerTime: 'Dinner (5:00 PM - 10:30 PM)',
                continue: 'Continue',
                email: 'Email',
                emailPlaceholder: 'your@email.com',
                phone: 'Phone',
                phonePlaceholder: '+52 123 456 7890',
                allergyToggle: 'Do you have any food allergies?',
                comments: 'Comments',
                commentsPlaceholder: 'Any special details?',
                back: 'Back',
                confirm: 'Confirm Reservation',
                eventTitle: 'Special Event!',
                eventMessage: 'For reservations of more than 19 people, please contact us directly.',
                understood: 'Understood',
                requiredFields: 'Please fill all required fields',
                selectTime: 'Please select a time',
                allergyRequired: 'If you have allergies, please specify them in the comments',
                allergyPlaceholder: 'Specify your allergies. Ex: Peanut allergy, gluten, shellfish...',
                dateOccupied: 'The date {date} at {time} is already booked. We suggest {nextTime}',
                dateOccupiedNoAlternative: 'The date {date} at {time} is already booked. Please choose another time or date.',
            }
        };

        let currentLang       = 'es';
        let isAllergyMode     = false;
        const unavailableDatesCache = {};

        const allTimes = [
            '07:30', '08:00', '08:30', '09:00', '09:30', '10:00',
            '10:30', '11:00', '11:30', '12:00', '12:30',
            '17:00', '17:30', '18:00', '18:30', '19:00', '19:30',
            '20:00', '20:30', '21:00', '21:30', '22:00', '22:30'
        ];

        const morningTimes = allTimes.slice(0, 11);
        const eveningTimes = allTimes.slice(11);

        const dateInput        = document.getElementById('date');
        const guestsInput      = document.getElementById('guests');
        const selectedTimeInput= document.getElementById('selectedTime');
        const commentsInput    = document.getElementById('comentarios');
        const allergyToggle    = document.getElementById('allergyToggle');
        const notification     = document.getElementById('notification');
        const capacityModal    = document.getElementById('capacityModal');
        const closeModalBtn    = document.getElementById('closeModal');

        document.addEventListener('DOMContentLoaded', initializeApp);

        function initializeApp() {
            updateDateMin();
            renderTimeSlots();
            attachEventListeners();
            updateLanguage();
        }

        function renderTimeSlots() {
            document.getElementById('morningSlots').innerHTML = morningTimes.map(time =>
                `<button type="button" class="time-btn" data-time="${time}">${time}</button>`
            ).join('');

            document.getElementById('eveningSlots').innerHTML = eveningTimes.map(time =>
                `<button type="button" class="time-btn" data-time="${time}">${time}</button>`
            ).join('');

            document.querySelectorAll('.time-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!this.classList.contains('disabled')) selectTime(this);
                });
            });

            filterPastTimes();
        }

        function selectTime(btn) {
            document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            selectedTimeInput.value = btn.dataset.time;
            checkAvailabilityForSelectedTime();
        }

        function attachEventListeners() {
            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    currentLang = this.dataset.lang;
                    document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    updateLanguage();
                });
            });

            dateInput.addEventListener('change', function() {
                filterPastTimes();
                checkDateAvailability();
            });

            allergyToggle.addEventListener('change', function() {
                isAllergyMode = this.checked;
                document.getElementById('esAlergico').value = this.checked ? '1' : '0';

                if (isAllergyMode) {
                    commentsInput.placeholder = translations[currentLang].allergyPlaceholder;
                    commentsInput.classList.add('required');
                } else {
                    commentsInput.placeholder = translations[currentLang].commentsPlaceholder;
                    commentsInput.classList.remove('required');
                }
            });

            closeModalBtn.addEventListener('click', hideModal);
            capacityModal.addEventListener('click', function(e) { if (e.target === this) hideModal(); });

            document.getElementById('reservationFormStep2').addEventListener('submit', function(e) {
                if (!validateStep2()) e.preventDefault();
            });
        }

        function validateStep1() {
            const name   = document.getElementById('name').value.trim();
            const date   = dateInput.value;
            const time   = selectedTimeInput.value;
            const guests = guestsInput.value;

            if (!name || !date || !guests) {
                showNotification('error', translations[currentLang].requiredFields);
                return;
            }

            if (!time) {
                showNotification('error', translations[currentLang].selectTime);
                return;
            }

            if (parseInt(guests, 10) > 19) { showModal(); return; }

            document.getElementById('finalNombre').value   = name;
            document.getElementById('finalFecha').value    = date;
            document.getElementById('finalPersonas').value = guests;
            document.getElementById('finalHora').value     = time;

            switchStep(1, 2);
        }

        function validateStep2() {
            const email    = document.getElementById('email').value.trim();
            const phone    = document.getElementById('telefono').value.trim();
            const comments = commentsInput.value.trim();

            if (!phone) {
                showNotification('error', translations[currentLang].requiredFields);
                return false;
            }

            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showNotification('error', 'Email invalido');
                return false;
            }

            if (isAllergyMode && !comments) {
                showNotification('error', translations[currentLang].allergyRequired);
                return false;
            }

            return true;
        }

        function switchStep(from, to) {
            document.getElementById(`step${from}`).classList.add('hidden');
            document.getElementById(`step${to}`).classList.remove('hidden');

            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.querySelector(`.step[data-step="${to}"]`).classList.add('active');

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function checkAvailabilityForSelectedTime() {
            const selectedTime = selectedTimeInput.value;
            if (!selectedTime) return;

            fetch(`check_availability.php?hora=${encodeURIComponent(selectedTime)}`)
                .then(r => r.json())
                .then(unavailableDates => {
                    unavailableDatesCache[selectedTime] = unavailableDates;
                    if (dateInput.value && unavailableDates.includes(dateInput.value)) {
                        handleDateOccupied(dateInput.value, selectedTime);
                    }
                })
                .catch(err => console.error('Error checking availability:', err));
        }

        function checkDateAvailability() {
            const date = dateInput.value;
            const time = selectedTimeInput.value;
            if (!date || !time) return;

            if (unavailableDatesCache[time] && unavailableDatesCache[time].includes(date)) {
                handleDateOccupied(date, time);
            } else {
                fetch(`check_availability.php?hora=${encodeURIComponent(time)}`)
                    .then(r => r.json())
                    .then(unavailableDates => {
                        unavailableDatesCache[time] = unavailableDates;
                        if (unavailableDates.includes(date)) handleDateOccupied(date, time);
                    });
            }
        }

        function handleDateOccupied(date, time) {
            const currentIndex = allTimes.indexOf(time);
            let nextAvailableTime = null;

            for (let i = currentIndex + 1; i < allTimes.length; i++) {
                if (!unavailableDatesCache[allTimes[i]] ||
                    !unavailableDatesCache[allTimes[i]].includes(date)) {
                    nextAvailableTime = allTimes[i];
                    break;
                }
            }

            if (nextAvailableTime) {
                const message = translations[currentLang].dateOccupied
                    .replace('{date}', formatDate(date))
                    .replace('{time}', time)
                    .replace('{nextTime}', nextAvailableTime);

                showNotification('warning', message);

                setTimeout(() => {
                    const nextBtn = document.querySelector(`.time-btn[data-time="${nextAvailableTime}"]`);
                    if (nextBtn) {
                        selectTime(nextBtn);
                        nextBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 2000);
            } else {
                const message = translations[currentLang].dateOccupiedNoAlternative
                    .replace('{date}', formatDate(date))
                    .replace('{time}', time);

                showNotification('error', message);

                selectedTimeInput.value = '';
                document.querySelectorAll('.time-btn.selected').forEach(btn => btn.classList.remove('selected'));
            }
        }

        function showNotification(type, message) {
            const icons = { error: '&#10060;', warning: '&#9888;', info: '&#8505;', success: '&#9989;' };
            notification.className = `notification ${type} show`;
            notification.innerHTML = `<span>${icons[type] || ''}</span><span>${message}</span>`;

            setTimeout(() => notification.classList.remove('show'), 6000);
        }

        function showModal() {
            capacityModal.classList.add('show');
            capacityModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function hideModal() {
            capacityModal.classList.remove('show');
            capacityModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function filterPastTimes() {
            const selectedDate = dateInput.value;
            if (!selectedDate) return;

            const now      = new Date();
            const todayStr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;

            document.querySelectorAll('.time-btn').forEach(btn => {
                if (selectedDate === todayStr) {
                    const [h, m] = btn.dataset.time.split(':').map(Number);
                    const btnTime = new Date();
                    btnTime.setHours(h, m, 0, 0);

                    if (btnTime <= now) {
                        btn.classList.add('disabled');
                        if (btn.classList.contains('selected')) {
                            btn.classList.remove('selected');
                            selectedTimeInput.value = '';
                            showNotification('warning', currentLang === 'es'
                                ? 'La hora seleccionada ya paso. Por favor elige otra hora.'
                                : 'The selected time has already passed. Please choose another time.');
                        }
                    } else {
                        btn.classList.remove('disabled');
                    }
                } else {
                    btn.classList.remove('disabled');
                }
            });
        }

        function updateDateMin() {
            const now   = new Date();
            const today = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
            dateInput.min = today;
            if (!dateInput.value) dateInput.value = today;
        }

        function formatDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            return date.toLocaleDateString(currentLang === 'es' ? 'es-ES' : 'en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
        }

        function updateLanguage() {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (translations[currentLang][key]) el.innerHTML = translations[currentLang][key];
            });

            document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                if (translations[currentLang][key]) el.placeholder = translations[currentLang][key];
            });

            if (isAllergyMode) commentsInput.placeholder = translations[currentLang].allergyPlaceholder;
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.type !== 'textarea' && e.target.type !== 'submit') {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
