/**
 * Layout partekatua — header (nabigazioa) eta footer-a orri guztietan injektatzen ditu.
 * Horrela leku BAKAR batean editatzen dira (190 orriren ordez).
 */
(function () {
    const path = window.location.pathname;

    function activeHref() {
        if (path.startsWith('/tour')) return '/tour/';
        if (path.startsWith('/giro')) return '/giro/';
        if (path.startsWith('/vuelta')) return '/vuelta/';
        if (path.startsWith('/klasikak')) return '/klasikak/';
        if (path.startsWith('/tresnak')) return '/tresnak/';
        return null;
    }

    const sekzioak = [
        ['tour', '/tour/', 'Tour'],
        ['giro', '/giro/', 'Giro'],
        ['vuelta', '/vuelta/', 'Vuelta'],
        ['klasikak', '/klasikak/', 'Klasikak'],
        ['grafikoak', '/tresnak/', 'Tresnak'],
    ];
    const act = activeHref();
    const items = sekzioak.map(([cls, href, label]) =>
        `<li><a class="${cls}${href === act ? ' active' : ''}" href="${href}">${label}</a></li>`).join('');

    const header = `
        <header>
            <h1><a href="/" style="color:inherit;text-decoration:none;">Aramaixo Porra</a></h1>
            <nav class="karrerak"><ul>${items}</ul></nav>
        </header>`;

    const footer = `
        <footer class="orri-footer">
            <div class="footer-iz">Aramaixo Porra</div>
            <div>Harremanetarako: <a href="mailto:harremana@aramaixoporra.eus">harremana@aramaixoporra.eus</a></div>
        </footer>`;

    function inject() {
        if (!document.querySelector('body > header')) {
            document.body.insertAdjacentHTML('afterbegin', header);
        }
        if (!document.querySelector('.orri-footer')) {
            document.body.insertAdjacentHTML('beforeend', footer);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inject);
    } else {
        inject();
    }
})();
