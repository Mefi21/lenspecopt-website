// Мобильное меню
function toggleMenu() {
    const nav = document.querySelector('.nav');
    if (nav.style.display === 'flex') {
        nav.style.display = 'none';
    } else {
        nav.style.display = 'flex';
        nav.style.flexDirection = 'column';
        nav.style.position = 'absolute';
        nav.style.top = '100%';
        nav.style.left = '0';
        nav.style.right = '0';
        nav.style.background = 'white';
        nav.style.padding = '20px';
        nav.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        nav.style.zIndex = '999';
    }
}

// Обработка формы
function handleFormSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.textContent = 'Отправляем...';

    fetch('send.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const msg = document.getElementById('successMsg');
            msg.style.display = 'block';
            form.reset();
            setTimeout(() => { msg.style.display = 'none'; }, 5000);
        } else {
            alert('Ошибка: ' + (data.error || 'Попробуйте ещё раз'));
        }
    })
    .catch(() => {
        alert('Ошибка соединения. Попробуйте снова.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Отправить заявку';
    });
}


// Плавная прокрутка
document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({behavior: 'smooth'});
            }
        });
    });
});