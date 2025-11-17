<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AI Tools Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-semibold text-gray-900">AI Tools Platform</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-700">
                            Welcome, {{ $user->name }} (ID: {{ $user->id }})
                        </span>
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">
                            Добре дошъл, {{ $user->name }}!
                        </h2>

                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h3 class="text-md font-medium text-gray-900 mb-2">Информация за профила:</h3>
                            <ul class="space-y-1 text-sm text-gray-600">
                                <li><strong>Име:</strong> {{ $user->name }}</li>
                                <li><strong>Email:</strong> {{ $user->email }}</li>
                                <li><strong>Желана роля:</strong> {{ ucfirst($user->role) }}</li>
                                <li><strong>Текущи права:</strong> {{ ucfirst($displayRole) }}</li>
                                <li><strong>Статус:</strong>
                                    @if($user->status === 'approved')
                                        <span class="text-green-600 font-medium">Одобрен</span>
                                    @else
                                        <span class="text-yellow-600 font-medium">Чака одобрение</span>
                                    @endif
                                </li>
                                <li><strong>Регистриран на:</strong> {{ $user->created_at->format('d.m.Y H:i') }}</li>
                            </ul>
                        </div>

                        @if($user->status === 'approved')
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <h3 class="text-md font-medium text-green-800 mb-2">✅ Профилът ви е одобрен</h3>
                                <p class="text-sm text-green-700">
                                    Имате пълни права като {{ ucfirst($user->role) }}. Можете да използвате всички функции на платформата.
                                </p>
                            </div>
                        @else
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <h3 class="text-md font-medium text-yellow-800 mb-2">⏳ Профилът ви чака одобрение</h3>
                                <p class="text-sm text-yellow-700">
                                    Администраторът ще прегледа заявката ви скоро. Засега имате базови права за разглеждане.
                                </p>
                            </div>
                        @endif

                        <div class="mt-6">
                            <h3 class="text-md font-medium text-gray-900 mb-4">Навигация:</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                @if($displayRole === 'owner' || $user->status === 'approved')
                                    <a href="#" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg p-4 transition-colors">
                                        <h4 class="font-medium text-indigo-900">AI Tools</h4>
                                        <p class="text-sm text-indigo-700">Управление на AI инструменти</p>
                                    </a>
                                @endif

                                @if($displayRole === 'owner')
                                    <a href="#" class="bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg p-4 transition-colors">
                                        <h4 class="font-medium text-red-900">User Management</h4>
                                        <p class="text-sm text-red-700">Одобрение на потребители</p>
                                    </a>
                                @endif

                                <a href="#" class="bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg p-4 transition-colors">
                                    <h4 class="font-medium text-gray-900">Profile</h4>
                                    <p class="text-sm text-gray-700">Управление на профила</p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
