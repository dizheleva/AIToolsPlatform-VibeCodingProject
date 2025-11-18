'use client';

import { useState, useEffect, useRef } from 'react';
import { Edit, Upload, X, Loader2 } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface User {
  id: number;
  name: string;
  email: string;
  avatar_url?: string | null;
}

interface EditProfileModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  user: User | null;
  onUpdate: (user: User) => void;
}

export default function EditProfileModal({
  open,
  onOpenChange,
  user,
  onUpdate,
}: EditProfileModalProps) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPasswordSection, setShowPasswordSection] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (user && open) {
      setName(user.name || '');
      setEmail(user.email || '');
      setAvatarPreview(user.avatar_url || null);
      setAvatarFile(null);
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
      setShowPasswordSection(false);
      setError(null);
      setSuccess(null);
    }
  }, [user, open]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      // Validate file type
      if (!file.type.startsWith('image/')) {
        setError('Моля, изберете валидно изображение.');
        return;
      }

      // Validate file size (2MB)
      const maxSize = 2 * 1024 * 1024; // 2MB
      if (file.size > maxSize) {
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        setError(`Изображението е твърде голямо (${fileSizeMB}MB). Максималният размер е 2MB.`);
        if (fileInputRef.current) {
          fileInputRef.current.value = '';
        }
        return;
      }

      setAvatarFile(file);
      setError(null);

      // Create preview
      const reader = new FileReader();
      reader.onloadend = () => {
        setAvatarPreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSave = async () => {
    if (!user) return;

    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      // Validate password if changing
      if (showPasswordSection) {
        if (!currentPassword || !newPassword || !confirmPassword) {
          setError('Моля, попълнете всички полета за парола.');
          setLoading(false);
          return;
        }
        if (newPassword !== confirmPassword) {
          setError('Потвърждението на паролата не съвпада.');
          setLoading(false);
          return;
        }
        if (newPassword.length < 8) {
          setError('Новата парола трябва да е поне 8 символа.');
          setLoading(false);
          return;
        }
      }

      // First upload avatar if changed
      if (avatarFile) {
        const formData = new FormData();
        formData.append('avatar', avatarFile);

        try {
          const avatarResponse = await fetch(
            'http://localhost:8201/api/user/avatar',
            {
              method: 'POST',
              credentials: 'include',
              body: formData,
            }
          );

          if (!avatarResponse.ok) {
            const avatarData = await avatarResponse.json().catch(() => ({}));
            const errorMessage =
              avatarData.message ||
              avatarData.errors?.avatar?.[0] ||
              'Грешка при качване на аватара. Моля, проверете размера на файла (макс. 2MB).';
            throw new Error(errorMessage);
          }

          const avatarData = await avatarResponse.json();
          if (avatarData.success && avatarData.user) {
            onUpdate(avatarData.user);
          }
        } catch (err) {
          throw new Error(
            err instanceof Error ? err.message : 'Грешка при качване на аватара.'
          );
        }
      }

      // Then update profile if name or email changed
      if (name !== user.name || email !== user.email) {
        try {
          const profileResponse = await fetch(
            'http://localhost:8201/api/user/profile',
            {
              method: 'PUT',
              credentials: 'include',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                name: name !== user.name ? name : undefined,
                email: email !== user.email ? email : undefined,
              }),
            }
          );

          if (!profileResponse.ok) {
            const profileData = await profileResponse.json().catch(() => ({}));
            const errorMessage =
              profileData.message ||
              profileData.errors?.email?.[0] ||
              profileData.errors?.name?.[0] ||
              'Грешка при актуализация на профила.';
            throw new Error(errorMessage);
          }

          const profileData = await profileResponse.json();
          if (profileData.success && profileData.user) {
            onUpdate(profileData.user);
            localStorage.setItem('user', JSON.stringify(profileData.user));
          }
        } catch (err) {
          throw new Error(
            err instanceof Error
              ? err.message
              : 'Грешка при актуализация на профила.'
          );
        }
      }

      // Change password if requested
      if (showPasswordSection && currentPassword && newPassword) {
        try {
          const passwordResponse = await fetch(
            'http://localhost:8201/api/user/change-password',
            {
              method: 'POST',
              credentials: 'include',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                current_password: currentPassword,
                password: newPassword,
                password_confirmation: confirmPassword,
              }),
            }
          );

          if (!passwordResponse.ok) {
            const passwordData = await passwordResponse.json().catch(() => ({}));
            const errorMessage =
              passwordData.message ||
              passwordData.errors?.current_password?.[0] ||
              passwordData.errors?.password?.[0] ||
              'Грешка при промяна на паролата.';
            throw new Error(errorMessage);
          }

          const passwordData = await passwordResponse.json();
          if (passwordData.success) {
            setCurrentPassword('');
            setNewPassword('');
            setConfirmPassword('');
            setShowPasswordSection(false);
            setSuccess('Паролата беше променена успешно.');
          }
        } catch (err) {
          throw new Error(
            err instanceof Error ? err.message : 'Грешка при промяна на паролата.'
          );
        }
      }

      if (!error) {
        setSuccess('Профилът беше актуализиран успешно.');
        setTimeout(() => {
          onOpenChange(false);
        }, 1500);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Възникна грешка.');
    } finally {
      setLoading(false);
    }
  };

  const handleRemoveAvatar = async () => {
    if (!user || !user.avatar_url) return;

    setLoading(true);
    setError(null);

    try {
      const response = await fetch('http://localhost:8201/api/user/avatar', {
        method: 'DELETE',
        credentials: 'include',
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Грешка при изтриване на аватара.');
      }

      const data = await response.json();
      if (data.success && data.user) {
        setAvatarPreview(null);
        setAvatarFile(null);
        onUpdate(data.user);
        localStorage.setItem('user', JSON.stringify(data.user));
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Възникна грешка.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[500px]" onClose={() => onOpenChange(false)}>
        <DialogHeader>
          <DialogTitle>Редактиране на профил</DialogTitle>
          <DialogDescription>
            Променете данните си и качете нов аватар
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          {/* Avatar Section */}
          <div className="flex flex-col items-center space-y-4">
            <div className="relative">
              {avatarPreview ? (
                <img
                  src={avatarPreview}
                  alt="Avatar preview"
                  className="h-24 w-24 rounded-full object-cover border-2 border-gray-300"
                />
              ) : (
                <div className="h-24 w-24 rounded-full bg-primary flex items-center justify-center text-white text-3xl font-bold border-2 border-gray-300">
                  {name.charAt(0).toUpperCase() || 'U'}
                </div>
              )}
              <button
                onClick={() => fileInputRef.current?.click()}
                className="absolute bottom-0 right-0 h-8 w-8 rounded-full bg-primary text-white flex items-center justify-center hover:bg-primary/90 transition-colors"
                disabled={loading}
                title="Качи аватар"
              >
                <Upload className="h-4 w-4" />
              </button>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp"
                onChange={handleFileChange}
                className="hidden"
              />
            </div>
            <div className="text-center">
              <p className="text-xs text-gray-500">
                Максимален размер: 2MB
              </p>
              <p className="text-xs text-gray-500">
                Поддържани формати: JPG, PNG, GIF, WebP
              </p>
            </div>
            {user?.avatar_url && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleRemoveAvatar}
                disabled={loading}
              >
                <X className="h-4 w-4 mr-2" />
                Премахни аватар
              </Button>
            )}
          </div>

          {/* Name Field */}
          <div className="space-y-2">
            <Label htmlFor="name">Име</Label>
            <Input
              id="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Вашето име"
              disabled={loading}
            />
          </div>

          {/* Email Field */}
          <div className="space-y-2">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="your@email.com"
              disabled={loading}
            />
          </div>

          {/* Password Change Section */}
          <div className="space-y-4 pt-4 border-t">
            <div className="flex items-center justify-between">
              <Label className="text-base font-semibold">
                Промяна на парола
              </Label>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => {
                  setShowPasswordSection(!showPasswordSection);
                  if (!showPasswordSection) {
                    setCurrentPassword('');
                    setNewPassword('');
                    setConfirmPassword('');
                  }
                }}
              >
                {showPasswordSection ? 'Скрий' : 'Промени'}
              </Button>
            </div>

            {showPasswordSection && (
              <div className="space-y-3">
                <div className="space-y-2">
                  <Label htmlFor="current_password">Текуща парола</Label>
                  <Input
                    id="current_password"
                    type="password"
                    value={currentPassword}
                    onChange={(e) => setCurrentPassword(e.target.value)}
                    placeholder="Въведете текущата парола"
                    disabled={loading}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="new_password">Нова парола</Label>
                  <Input
                    id="new_password"
                    type="password"
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    placeholder="Въведете нова парола (мин. 8 символа)"
                    disabled={loading}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="confirm_password">Потвърди новата парола</Label>
                  <Input
                    id="confirm_password"
                    type="password"
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    placeholder="Повторете новата парола"
                    disabled={loading}
                  />
                </div>
              </div>
            )}
          </div>

          {/* Success Message */}
          {success && (
            <div className="text-sm text-green-600 bg-green-50 p-3 rounded-md">
              {success}
            </div>
          )}

          {/* Error Message */}
          {error && (
            <div className="text-sm text-red-600 bg-red-50 p-3 rounded-md">
              {error}
            </div>
          )}
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={loading}
          >
            Отказ
          </Button>
          <Button
            type="button"
            onClick={handleSave}
            disabled={
              loading ||
              (!avatarFile &&
                name === user?.name &&
                email === user?.email &&
                !showPasswordSection)
            }
          >
            {loading && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
            Запази
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

