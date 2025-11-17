<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Акаунт одобрен</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 28px;">✨ AI Tools Platform</h1>
    </div>
    
    <div style="background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e5e7eb; border-top: none;">
        <h2 style="color: #10b981; margin-top: 0;">Вашият акаунт е одобрен! 🎉</h2>
        
        <p>Здравейте, <strong>{{ $user->name }}</strong>!</p>
        
        <p>Радваме се да ви информираме, че вашият акаунт в AI Tools Platform е успешно одобрен от администратор.</p>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981;">
            <p style="margin: 0;"><strong>Вашата роля:</strong> {{ ucfirst($user->role) }}</p>
            <p style="margin: 5px 0 0 0;"><strong>Статус:</strong> <span style="color: #10b981;">Одобрен</span></p>
        </div>
        
        <p>Сега можете да:</p>
        <ul style="padding-left: 20px;">
            <li>Добавяте нови AI инструменти</li>
            <li>Управлявате създадените от вас инструменти</li>
            <li>Харесвате и коментирате инструменти</li>
            <li>Пълноценно да използвате всички функции на платформата</li>
        </ul>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ env('APP_URL', 'http://localhost:3000') }}/dashboard" 
               style="display: inline-block; background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Влезте в платформата
            </a>
        </div>
        
        <p style="color: #6b7280; font-size: 14px; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
            Ако имате въпроси или нужда от помощ, моля свържете се с администратора на платформата.
        </p>
    </div>
</body>
</html>

