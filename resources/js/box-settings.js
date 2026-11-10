function luminance(hex) {
    const [r, g, b] = hex.slice(1).match(/../g).map((value) => {
        const x = parseInt(value, 16) / 255;
        return x <= .04045 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4;
    });
    return .2126 * r + .7152 * g + .0722 * b;
}

function foreground(hex) {
    const value = luminance(hex);
    return (value + .05) / .05 >= 1.05 / (value + .05) ? '#000000' : '#FFFFFF';
}
function accent(hex) {
    const channels = hex.slice(1).match(/../g).map(value => parseInt(value, 16));
    for (let step=0;step<=20;step++) {
        const candidate = '#'+channels.map(value => Math.round(value+(255-value)*step/20).toString(16).padStart(2,'0')).join('');
        if ((luminance(candidate)+.05)/.07 >= 4.5) return candidate;
    }
    return '#FFFFFF';
}

const form = document.querySelector('[data-box-settings]');
if (form) {
    const preview = form.querySelector('[data-identity-preview]');
    form.querySelectorAll('[data-color-field]').forEach((field) => {
        const picker = field.querySelector('[data-color-picker]');
        const hex = field.querySelector('[data-color-hex]');
        const apply = (value) => {
            if (!/^#[a-fA-F0-9]{6}$/.test(value)) return;
            picker.value = value;
            const variable = field.dataset.colorField === 'primary_color' ? '--box-primary' : '--box-secondary';
            preview.style.setProperty(variable, value);
            preview.style.setProperty(`${variable}-foreground`, foreground(value));
            preview.style.setProperty(variable === '--box-primary' ? '--box-accent' : '--box-secondary-accent', accent(value));
            if (variable === '--box-primary') preview.style.setProperty('--box-primary-text', foreground(value));
        };
        picker.addEventListener('input', () => { hex.value = picker.value; apply(hex.value); });
        hex.addEventListener('input', () => apply(hex.value));
        apply(hex.value);
    });
    form.querySelector('[data-box-name]').addEventListener('input', (event) => {
        form.querySelector('[data-box-name-preview]').textContent = event.target.value;
    });
    let objectUrl;
    const logo = form.querySelector('[data-logo-preview]');
    const initialSrc = logo.getAttribute('src');
    form.querySelector('[data-logo-input]').addEventListener('change', (event) => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = event.target.files[0];
        const remove = form.querySelector('[data-logo-remove]');
        if (remove) remove.checked = false;
        if (file) {
            objectUrl = URL.createObjectURL(file);
            logo.src = objectUrl;
            logo.hidden = false;
        } else {
            if (initialSrc) logo.src = initialSrc;
            logo.hidden = !initialSrc;
        }
    });
    form.querySelector('[data-logo-remove]')?.addEventListener('change', (event) => {
        if (event.target.checked) {
            form.querySelector('[data-logo-input]').value = '';
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            logo.hidden = true;
        } else {
            if (initialSrc) logo.src = initialSrc;
            logo.hidden = !initialSrc;
        }
    });
    window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
}

const registration = document.querySelector('[data-registration-theme]');
if (registration) {
    const primary = registration.querySelector('[name=primary_color]');
    const apply = () => {
        if (!/^#[a-fA-F0-9]{6}$/.test(primary.value)) return;
        registration.style.setProperty('--box-primary', primary.value);
        registration.style.setProperty('--box-accent', accent(primary.value));
        registration.style.setProperty('--box-primary-text', foreground(primary.value));
    };
    primary.addEventListener('input', apply);
    apply();
}
