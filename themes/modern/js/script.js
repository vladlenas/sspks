function toggleClassElement(className, element) {
    if (!element) return;

    if (element.classList.contains(className)) {
        element.classList.remove(className);
    } else {
        element.classList.add(className);
    }
}

function toggleBeta() {
    var input = document.getElementById('switch-beta');
    if (!input) return;

    var elements = document.getElementsByClassName('spk-beta');
    var display = input.checked ? '' : 'none';

    for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = display;
    }
}

function toggleDetails(button) {
    if (!button) return;

    var card = button.closest('.package-card');
    if (!card) return;

    var details = card.querySelector('.spk-details');
    if (!details) return;

    toggleClassElement('spk-details-hidden', details);
    button.textContent = details.classList.contains('spk-details-hidden')
        ? 'More info'
        : 'Hide info';
}

function filterModels(filter) {
    var cards = document.querySelectorAll('[data-model-family]');
    var architectureCards = document.querySelectorAll('[data-arch-filter]');

    for (var i = 0; i < cards.length; i++) {
        var family = cards[i].getAttribute('data-model-family');
        cards[i].style.display = (filter === 'all' || family === filter) ? '' : 'none';
    }

    for (var j = 0; j < architectureCards.length; j++) {
        var value = architectureCards[j].getAttribute('data-arch-filter');
        architectureCards[j].classList.toggle('architecture-active', value === filter);
    }
}

function initArchitectureFilters() {
    var controls = document.querySelectorAll('[data-arch-filter]');
    if (!controls.length) return;

    for (var i = 0; i < controls.length; i++) {
        controls[i].addEventListener('click', function () {
            filterModels(this.getAttribute('data-arch-filter'));
        });
    }

    var families = ['x86_64', 'armv8', 'armv7'];

    for (var j = 0; j < families.length; j++) {
        var count = document.querySelectorAll('[data-model-family="' + families[j] + '"]').length;
        var counter = document.querySelector('[data-count-for="' + families[j] + '"]');

        if (counter) {
            counter.textContent = count + ' models';
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initArchitectureFilters();
    toggleBeta();
});
