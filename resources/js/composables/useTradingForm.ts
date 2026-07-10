import { ref, computed } from 'vue';

interface OrderForm {
    price: string;
    quantity: string;
    total: string;
}

export function useTradingForm(currentPrice: () => string) {
    const orderForm = ref<OrderForm>({
        price: '',
        quantity: '',
        total: '',
    });

    const orderType = ref<'limit' | 'market'>('limit');
    const activeOrderTab = ref<'buy' | 'sell'>('buy');

    const estimatedFee = computed(() => {
        if (!orderForm.value.total) {
            return '0.00';
        }

        const total = parseFloat(orderForm.value.total);

        return (total * 0.001).toFixed(8); // 0.1% fee
    });

    const totalWithFee = computed(() => {
        if (!orderForm.value.total) {
            return '0.00';
        }

        const total = parseFloat(orderForm.value.total);
        const fee = parseFloat(estimatedFee.value);

        return (total + fee).toFixed(8);
    });

    function setOrderTab(tab: 'buy' | 'sell') {
        activeOrderTab.value = tab;
        orderForm.value = { price: '', quantity: '', total: '' };
    }

    function setOrderType(type: 'limit' | 'market') {
        orderType.value = type;

        if (type === 'market') {
            orderForm.value.price = currentPrice();
        } else {
            orderForm.value.price = '';
        }

        calculateTotal();
    }

    function calculateTotal() {
        const price = orderForm.value.price
            ? parseFloat(orderForm.value.price)
            : 0;
        const quantity = orderForm.value.quantity
            ? parseFloat(orderForm.value.quantity)
            : 0;

        if (price && quantity) {
            const total = price * quantity;
            orderForm.value.total = total.toFixed(8);
        } else {
            orderForm.value.total = '';
        }
    }

    function validateNumberInput(event: Event) {
        const input = event.target as HTMLInputElement;
        const value = input.value;

        const regex = /^[0-9]*\.?[0-9]*$/;

        if (!regex.test(value)) {
            input.value = value.replace(/[^0-9.]/g, '');

            const parts = input.value.split('.');

            if (parts.length > 2) {
                input.value = parts[0] + '.' + parts.slice(1).join('');
            }

            if (input === document.activeElement) {
                const field = input.getAttribute('data-field');

                if (field === 'price') {
                    orderForm.value.price = input.value;
                } else if (field === 'quantity') {
                    orderForm.value.quantity = input.value;
                }
            }
        }
    }

    function setPercentage(percent: number, balance: string, price?: string) {
        const balanceValue = parseFloat(balance);
        const priceToUse = price || orderForm.value.price;

        if (!priceToUse) {
            throw new Error('Please enter price first');
        }

        if (!balanceValue || balanceValue === 0) {
            throw new Error('Insufficient balance');
        }

        const priceValue = parseFloat(priceToUse);

        if (activeOrderTab.value === 'buy') {
            const availableUsdt = balanceValue * (percent / 100);
            orderForm.value.quantity = (availableUsdt / priceValue).toFixed(8);
        } else {
            orderForm.value.quantity = (balanceValue * (percent / 100)).toFixed(
                8,
            );
        }

        calculateTotal();
    }

    function resetForm() {
        orderForm.value = { price: '', quantity: '', total: '' };
    }

    return {
        orderForm,
        orderType,
        activeOrderTab,
        estimatedFee,
        totalWithFee,
        setOrderTab,
        setOrderType,
        calculateTotal,
        validateNumberInput,
        setPercentage,
        resetForm,
    };
}
