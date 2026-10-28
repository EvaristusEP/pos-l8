

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const formatCurrency = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;

const escapeHtml = (value) => String(value ?? '')
	.replaceAll('&', '&amp;')
	.replaceAll('<', '&lt;')
	.replaceAll('>', '&gt;')
	.replaceAll('"', '&quot;')
	.replaceAll("'", '&#39;');

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const normalizeQuantityInput = (input) => {
	if (!input) {
		return 0;
	}

	const value = input.value.trim();

	if (/^\d+$/.test(value)) {
		input.value = String(Number.parseInt(value, 10));
	}

	return Number(input.value || 0);
};

const buildCartItemHtml = (item) => {
	const toppings = item.topping_names?.length ? item.topping_names.map(escapeHtml).join(', ') : '-';
	const grade = escapeHtml(item.matcha_grade_name || '-');
	const origin = escapeHtml(item.matcha_origin_name || '-');

	return `
		<article class="rounded-[1.5rem] border border-emerald-100 bg-emerald-50/60 p-4 shadow-sm">
			<div class="flex items-start justify-between gap-3">
				<div class="min-w-0">
					<h3 class="break-words font-bold text-slate-950">${escapeHtml(item.menu_name)}</h3>
					${item.matcha_grade_name ? `<p class="mt-1 break-words text-xs text-slate-600">${grade}${item.matcha_origin_name ? ` · ${origin}` : ''}</p>` : ''}
					${item.topping_names?.length ? `<p class="mt-1 break-words text-xs text-slate-500">${toppings}</p>` : ''}
				</div>
				<button type="button" data-cart-action="remove" data-cart-index="${item.index}" class="text-xs font-semibold text-rose-600 transition hover:text-rose-700">Remove</button>
			</div>

			<div class="mt-3 flex items-center justify-between"><div class="flex items-center gap-2"><button type="button" data-cart-action="decrease" data-cart-index="${item.index}" class="qty-button">−</button><span class="w-6 text-center text-sm font-bold">${item.quantity}</span><button type="button" data-cart-action="increase" data-cart-index="${item.index}" class="qty-button">+</button></div><strong class="whitespace-nowrap text-sm">${formatCurrency(item.line_total)}</strong></div>
		</article>
	`;
};

const buildEmptyCartHtml = () => `
	<div class="rounded-[1.5rem] border border-dashed border-emerald-200 bg-emerald-50/50 p-6 text-center">
		<p class="text-base font-bold text-emerald-950">Cart masih kosong</p>
		<p class="mt-2 text-sm leading-6 text-slate-600">Pilih menu dari area tengah untuk menambah item transaksi.</p>
	</div>
`;

const syncCartState = (state) => {
	if (!state || typeof state !== 'object') {
		throw new Error('Cart tidak dapat diperbarui. Muat ulang halaman lalu coba lagi.');
	}
	const cartCountNodes = document.querySelectorAll('[data-cart-item-count]');
	const cartSubtotalNodes = document.querySelectorAll('[data-cart-subtotal]');
	const cartItemsContainer = document.querySelector('[data-cart-items]');

	cartCountNodes.forEach((node) => {
		node.textContent = `${state.item_count || 0} items`;
	});

	cartSubtotalNodes.forEach((node) => {
		node.textContent = formatCurrency(state.subtotal || 0);
	});

	if (cartItemsContainer) {
		cartItemsContainer.innerHTML = state.empty ? buildEmptyCartHtml() : state.items.map(buildCartItemHtml).join('');
	}

	const checkoutCashInput = document.querySelector('[data-cash-input]');
	if (checkoutCashInput) {
		const subtotal = Number(state.subtotal || 0);
		const cashValue = Number(checkoutCashInput.value || 0);
		const change = Math.max(0, cashValue - subtotal);

		document.querySelectorAll('[data-cash-change]').forEach((node) => {
			node.textContent = formatCurrency(change);
		});
		document.querySelectorAll('[data-checkout-subtotal]').forEach((node) => {
			node.textContent = formatCurrency(subtotal);
		});
	}
};

const requestCartMutation = async (url, method, formData = null) => {
	const response = await fetch(url, {
		method,
		headers: {
			Accept: 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
			'X-CSRF-TOKEN': getCsrfToken(),
		},
		body: formData,
	});

	const data = await response.json().catch(() => null);

	if (!response.ok) {
		const message = data?.message || Object.values(data?.errors || {})
			.flat()
			.filter(Boolean)
			.join('\n') || 'Request gagal diproses.';

		throw new Error(message);
	}

	if (!data) {
		throw new Error('Respons cart tidak valid. Muat ulang halaman lalu coba lagi.');
	}

	return data;
};

const bindCartForms = () => {
	document.querySelectorAll('[data-menu-form]').forEach((form) => {
		const gradeInputs = form.querySelectorAll('[data-matcha-grade-select]');
		const originWrapper = form.querySelector('[data-matcha-origin-wrapper]');
		const originSelect = form.querySelector('[data-matcha-origin-select]');
		const quantityInput = form.querySelector('input[name="quantity"]');
		const submitButton = form.querySelector('[data-add-to-cart-button]');
		const selectedGrade = () => Array.from(gradeInputs).find((input) => input.checked);

		const syncButtonState = () => {
			const gradeRequired = gradeInputs.length > 0;
			const grade = selectedGrade();
			const gradeSelected = !gradeRequired || Boolean(grade);
			const ceremonial = grade?.dataset.isCeremonial === 'true';
			const ceremonialSelected = !ceremonial || Boolean(originSelect?.value);
			const quantitySelected = normalizeQuantityInput(quantityInput) >= 1;

			if (originWrapper) {
				originWrapper.classList.toggle('hidden', !ceremonial);
				if (!ceremonial && originSelect) {
					originSelect.value = '';
				}
			}

			if (submitButton) {
				submitButton.disabled = !(gradeSelected && ceremonialSelected && quantitySelected);
			}
		};

		gradeInputs.forEach((input) => input.addEventListener('change', syncButtonState));
		originSelect?.addEventListener('change', syncButtonState);
		quantityInput?.addEventListener('input', syncButtonState);
		syncButtonState();

		form.addEventListener('submit', async (event) => {
			event.preventDefault();
			normalizeQuantityInput(quantityInput);

			try {
				const data = await requestCartMutation(form.action, 'POST', new FormData(form));
				syncCartState(data);
				form.reset();
				syncButtonState();
			} catch (error) {
				window.alert(error.message);
			}
		});
	});
};

const bindCartActions = () => {
	const cartItemsContainer = document.querySelector('[data-cart-items]');

	if (!cartItemsContainer) {
		return;
	}

	cartItemsContainer.addEventListener('click', async (event) => {
		const actionButton = event.target.closest('[data-cart-action]');

		if (!actionButton) {
			return;
		}

		const action = actionButton.dataset.cartAction;
		const index = actionButton.dataset.cartIndex;
		const routes = window.matchaPosRoutes || {};
		const routeMap = {
			increase: `${routes.cartBase}/${index}/increase`,
			decrease: `${routes.cartBase}/${index}/decrease`,
			remove: `${routes.cartBase}/${index}`,
		};
		const methodMap = {
			increase: 'PATCH',
			decrease: 'PATCH',
			remove: 'DELETE',
		};

		try {
			const data = await requestCartMutation(routeMap[action], methodMap[action]);
			syncCartState(data);
		} catch (error) {
			window.alert(error.message);
		}
	});

	const clearButton = document.querySelector('[data-cart-clear]');

	clearButton?.addEventListener('click', async () => {
		try {
			const data = await requestCartMutation(clearButton.dataset.url, 'DELETE');
			syncCartState(data);
		} catch (error) {
			window.alert(error.message);
		}
	});
};

const bindCheckoutForm = () => {
	const checkoutForm = document.querySelector('[data-checkout-form]');

	if (!checkoutForm) {
		return;
	}

	const paymentRadios = checkoutForm.querySelectorAll('input[name="payment_method"]');
	const cashPanel = checkoutForm.querySelector('[data-payment-panel="cash"]');
	const qrisPanel = checkoutForm.querySelector('[data-payment-panel="qris"]');
	const cashInput = checkoutForm.querySelector('[data-cash-input]');
	const submitButton = checkoutForm.querySelector('[data-checkout-submit]');
	const cashLabel = checkoutForm.dataset.cashSubmitLabel || 'Complete Payment';
	const qrisLabel = checkoutForm.dataset.qrisSubmitLabel || 'Mark As Paid';
	const subtotal = Number(checkoutForm.dataset.subtotal || 0);

	if (cashInput) {
		cashInput.placeholder = '';
	}

	const syncPaymentUi = () => {
		const selected = checkoutForm.querySelector('input[name="payment_method"]:checked')?.value || 'cash';

		cashPanel?.classList.toggle('hidden', selected !== 'cash');
		qrisPanel?.classList.toggle('hidden', selected !== 'qris');

		if (submitButton) {
			submitButton.textContent = selected === 'qris' ? qrisLabel : cashLabel;
		}
	};

	const syncCashChange = () => {
		const value = Number(cashInput?.value || 0);
		const change = Math.max(0, value - subtotal);

		document.querySelectorAll('[data-cash-change]').forEach((node) => {
			node.textContent = formatCurrency(change);
		});
	};

	paymentRadios.forEach((radio) => radio.addEventListener('change', syncPaymentUi));
	cashInput?.addEventListener('input', syncCashChange);

	checkoutForm.addEventListener('submit', async (event) => {
		if (!checkoutForm.dataset.ajaxCheckout) {
			return;
		}

		event.preventDefault();

		try {
			const data = await requestCartMutation(checkoutForm.action, 'POST', new FormData(checkoutForm));
			if (data.redirect) {
				window.location.href = data.redirect;
			}
		} catch (error) {
			window.alert(error.message);
		}
	});

	syncPaymentUi();
	syncCashChange();
};

const bindMenuSearch = () => {
	const searchInput = document.querySelector('[data-menu-search]');

	if (!searchInput) {
		return;
	}

	const cards = Array.from(document.querySelectorAll('[data-menu-card]'));
	const categories = Array.from(document.querySelectorAll('[data-menu-category]'));
	const emptyState = document.querySelector('[data-menu-search-empty]');
	const status = document.querySelector('[data-menu-search-status]');

	const filterMenus = () => {
		const keyword = searchInput.value.trim().toLocaleLowerCase('id-ID');
		let visibleCount = 0;

		cards.forEach((card) => {
			const matches = !keyword || (card.dataset.menuSearchText || '').includes(keyword);
			card.classList.toggle('hidden', !matches);
			visibleCount += matches ? 1 : 0;
		});

		categories.forEach((category) => {
			category.classList.toggle('hidden', !category.querySelector('[data-menu-card]:not(.hidden)'));
		});

		emptyState?.classList.toggle('hidden', visibleCount > 0);
		if (status) {
			status.textContent = keyword ? `${visibleCount} menu ditemukan untuk “${searchInput.value.trim()}”.` : 'Menampilkan semua menu.';
		}
	};

	searchInput.addEventListener('input', filterMenus);
};

const bindOrderFulfillment = () => {
	const panel = document.querySelector('[data-order-fulfillment]');

	if (!panel) {
		return;
	}

	const hasPastry = panel.dataset.hasPastry === 'true';
	const hasBeverage = panel.dataset.hasBeverage === 'true';
	const selfServicePanel = panel.querySelector('[data-fulfillment-self-service]');
	const tablePanel = panel.querySelector('[data-fulfillment-table]');
	const pagerInput = panel.querySelector('input[name="pager_number"]');
	const tableInput = panel.querySelector('input[name="table_number"]');

	if (pagerInput) {
		pagerInput.value = '';
		pagerInput.placeholder = '';
		pagerInput.autocomplete = 'off';
		pagerInput.closest('label')?.querySelector('span')?.remove();
	}

	if (tableInput) {
		tableInput.value = '';
		tableInput.autocomplete = 'off';
	}

	const syncFulfillment = () => {
		const dineIn = panel.querySelector('input[name="service_type"]:checked')?.value === 'dine_in';
		const tableDelivery = hasPastry && dineIn;
		const requiresPager = hasBeverage || !tableDelivery;

		selfServicePanel?.classList.toggle('hidden', !requiresPager);
		tablePanel?.classList.toggle('hidden', !tableDelivery);
		if (pagerInput) {
			pagerInput.disabled = !requiresPager;
			pagerInput.required = requiresPager;
		}
		if (tableInput) tableInput.required = tableDelivery;
	};

	panel.querySelectorAll('input[name="service_type"]').forEach((input) => input.addEventListener('change', syncFulfillment));
	syncFulfillment();
};

document.addEventListener('DOMContentLoaded', () => {
	bindCartForms();
	bindCartActions();
	bindCheckoutForm();
	bindMenuSearch();
	bindOrderFulfillment();
});
