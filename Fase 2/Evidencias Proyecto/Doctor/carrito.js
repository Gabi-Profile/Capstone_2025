document.addEventListener('DOMContentLoaded', function() {
    // Agregar insumo al carrito
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const insumo = this.dataset.insumo;
            fetch('carrito_insumos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'add', insumo })
            })
            .then(response => response.json())
            .then(data => actualizarCarrito(data));
        });
    });

    // Escuchar clicks en botones del carrito
    document.addEventListener('click', function(event) {
        const target = event.target;
        const insumo = target.dataset.insumo;

        if (target.classList.contains('remove-from-cart')) {
            fetch('carrito_insumos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'remove', insumo })
            })
            .then(response => response.json())
            .then(data => actualizarCarrito(data));
        }

        if (target.classList.contains('increase-qty')) {
            fetch('carrito_insumos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'add', insumo })
            })
            .then(response => response.json())
            .then(data => actualizarCarrito(data));
        }

        if (target.classList.contains('decrease-qty')) {
            fetch('carrito_insumos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'decrease', insumo })
            })
            .then(response => response.json())
            .then(data => actualizarCarrito(data));
        }
    });

    function actualizarCarrito(carrito) {
        const carritoContainer = document.querySelector('#carrito-items');
        carritoContainer.innerHTML = '';

        if (Object.keys(carrito).length === 0) {
            carritoContainer.innerHTML = '<li>El carrito está vacío</li>';
            return;
        }

        Object.entries(carrito).forEach(([insumo, cantidad]) => {
            const listItem = document.createElement('li');
            listItem.innerHTML = `
                <span>${insumo} (x${cantidad})</span>
                <button class='decrease-qty' data-insumo='${insumo}'>−</button>
                <button class='increase-qty' data-insumo='${insumo}'>+</button>
                <button class='remove-from-cart' data-insumo='${insumo}'>Eliminar</button>
            `;
            carritoContainer.appendChild(listItem);
        });
    }
});
