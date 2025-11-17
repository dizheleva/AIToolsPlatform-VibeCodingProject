<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Акаунт отхвърлен</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 28px;">✨ AI Tools Platform</h1>
    </div>
    
    <div style="background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e5e7eb; border-top: none;">
        <h2 style="color: #ef4444; margin-top: 0;">Вашият акаунт е отхвърлен</h2>
        
        <p>Здравейте, <strong>{{ $user->name }}</strong>!</p>
        
        <p>Съжаляваме да ви информираме, че вашият акаунт в AI Tools Platform е отхвърлен от администратор.</p>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ef4444;">
            <p style="margin: 0;"><strong>Вашата роля:</strong> {{ ucfirst($user->role) }}</p>
            <p style="margin: 5px 0 0 0;"><strong>Статус:</strong> <span style="color: #ef4444;">Отхвърлен</span></p>
        </div>
        
        <p>Ако смятате, че това е грешка или имате въпроси относно решението, моля свържете се с администратора на платформата за допълнителна информация.</p>
        
        <p style="color: #6b7280; font-size: 14px; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
            Благодарим ви за интереса към AI Tools Platform.
        </p>
    </div>
</body>
</html>

