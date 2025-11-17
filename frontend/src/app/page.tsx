import Link from "next/link";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Sparkles } from "lucide-react";

export default function Home() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="text-center mb-8">
          <div className="flex items-center justify-center mb-4">
            <Sparkles className="h-12 w-12 text-primary" />
          </div>
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            AI Tools Platform
          </h1>
          <p className="text-lg text-gray-600">
            Вътрешна платформа за споделяне на AI инструменти
          </p>
        </div>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <Card className="shadow-lg">
          <CardHeader>
            <CardTitle className="text-center">Добре дошли</CardTitle>
            <CardDescription className="text-center">
              Влезте или създайте нов акаунт
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <Link href="/login" className="block">
              <Button className="w-full" size="lg">
                Вход
              </Button>
            </Link>

            <Link href="/register" className="block">
              <Button variant="outline" className="w-full" size="lg">
                Регистрация
              </Button>
            </Link>

            <div className="relative mt-6">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-border" />
              </div>
              <div className="relative flex justify-center text-sm">
                <span className="px-2 bg-card text-muted-foreground">Примерни акаунти</span>
              </div>
            </div>

            <div className="mt-6 space-y-2 text-sm">
              <div className="p-3 bg-muted/50 rounded-md">
                <div className="font-medium text-foreground mb-1">Админ</div>
                <div className="text-muted-foreground">ivan@admin.local / password</div>
              </div>
              <div className="p-3 bg-muted/50 rounded-md">
                <div className="font-medium text-foreground mb-1">Frontend</div>
                <div className="text-muted-foreground">elena@frontend.local / password</div>
              </div>
              <div className="p-3 bg-muted/50 rounded-md">
                <div className="font-medium text-foreground mb-1">Backend</div>
                <div className="text-muted-foreground">petar@backend.local / password</div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
