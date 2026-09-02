<!doctype html>
<html lang="it">
<body style="font-family: Arial, sans-serif; color: #222;">
    <p>Buongiorno,</p>

    <p>
        in allegato trovi l'export Excel dei corrispettivi ordini per
        <strong>{{ $month->translatedFormat('F Y') }}</strong>.
    </p>

    <p>Ordini inclusi: <strong>{{ $ordersCount }}</strong>.</p>

    <p style="color: #666; font-size: 13px;">
        Il file include tutti gli ordini del mese, indipendentemente dallo stato.
    </p>
</body>
</html>
