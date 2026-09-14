<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 50px;
        }

        .payment-card {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        .payment-card h2 {
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        input {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>

<body>

<div class="payment-card">

    <h2>Make Payment</h2>

    <form method="POST" action="{{ route('payment.initiate') }}">

        @csrf

        <div class="form-group">
            <label>Amount</label>
            <input
                type="number"
                name="amount"
                value="100"
                min="1"
                step="0.01"
                required
            >
        </div>

        <div class="form-group">
            <label>Name</label>
            <input
                type="text"
                name="cus_name"
                value="Jamil Hossain"
                required
            >
        </div>

        <div class="form-group">
            <label>Email</label>
            <input
                type="email"
                name="cus_email"
                value="test@example.com"
                required
            >
        </div>

        <div class="form-group">
            <label>Phone</label>
            <input
                type="text"
                name="cus_phone"
                value="01700000000"
                required
            >
        </div>

        <div class="form-group">
            <label>Order ID</label>
            <input
                type="number"
                name="order_id"
                value="1"
            >
        </div>

        <button type="submit">
            Pay Now
        </button>

    </form>

</div>

</body>
</html>