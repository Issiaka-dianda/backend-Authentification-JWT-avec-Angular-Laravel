# Authentification JWT avec Angular & Laravel + Gestion des Permissions (Spatie)

## Table des matières
- [Introduction](#introduction)
- [Backend avec Laravel](#backend-avec-laravel)  
  - [Configuration et installation](#configuration-et-installation)  
  - [Authentification JWT](#authentification-jwt)  
  - [Permissions avec Spatie](#permissions-avec-spatie)  
  - [Contrôleurs et routes](#contrôleurs-et-routes)  
- [Frontend avec Angular](#frontend-avec-angular)  
  - [Configuration du projet](#configuration-du-projet)  
  - [Services d’authentification](#services-dauthentification)  
  - [Intercepteurs HTTP](#intercepteurs-http)  
  - [Gardes et directives](#gardes-et-directives)  
- [Améliorations de sécurité](#améliorations-de-sécurité)  
- [Exemples d’application](#exemples-dapplication)  
- [Résumé des bonnes pratiques](#résumé-des-bonnes-pratiques)  

---

## Introduction

Ce cours vous guide dans l’implémentation d’un système d’authentification basé sur JWT sécurisé, avec un backend Laravel et un frontend Angular, enrichi d’une gestion granulaire des permissions grâce à Spatie.

### Objectifs
- Comprendre la différence entre authentification et autorisation  
- Construire une API Laravel sécurisée avec authentification JWT  
- Mettre en œuvre le contrôle d’accès basé sur les rôles avec Spatie  
- Créer une application Angular avec un flux complet d’authentification  
- Sécuriser le stockage des tokens et la communication API  

### Technologies utilisées
- **Backend** : Laravel, `tymon/jwt-auth`, `spatie/laravel-permission`  
- **Frontend** : Angular, PrimeNG, `crypto-js`  
- **Outils** : Composer, NPM, Postman/Insomnia  

### Concepts clés

#### Authentification vs Autorisation
- **Authentification** : vérifie l’identité de l’utilisateur (qui vous êtes)  
- **Autorisation** : détermine ce que l’utilisateur peut faire (quelles ressources il peut accéder)  
- **Sans état vs avec état** : JWT permet une authentification sans état, idéale pour les API modernes  

#### JSON Web Tokens (JWT)
JWT est un format compact et autonome, structuré en trois parties :  
1. **Header** : type de token et algorithme  
2. **Payload** : claims (données utilisateur, permissions, expiration)  
3. **Signature** : assure l’intégrité du token  

Exemple :
```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsIm5hbWUiOiJKb2huIERvZSIsInJvbGUiOiJBZG1pbiIsImlhdCI6MTY2OTAwODAwMCwiZXhwIjoxNjY5MDEyMDAwfQ.4wKpQn9gKc9ZK7qN4n6w3eY8vF1VbP0kq2kKc8kZK9k
```

**Avantages** : scalabilité (stateless), portabilité entre services, info d’autorisation intégrée, simplification de l’authentification API.

**Cycle de vie** :  
1. Login → réception du JWT  
2. Stockage (localStorage/sessionStorage/cookie sécurisé)  
3. Envoi du token dans l’en-tête Authorization  
4. Validation côté serveur à chaque requête  
5. Expiration ou invalidation à la déconnexion  

#### Contrôle d’accès basé sur les rôles (RBAC)
- **Rôles** : groupes de permissions (Admin, Manager, User)  
- **Permissions** : actions précises  
- **Modèles** : hiérarchique ou plat  

---

## Backend avec Laravel

### Configuration et installation

#### Création du projet
```bash
composer create-project laravel/laravel jwt-auth-app
cd jwt-auth-app
php artisan serve
```

#### Configuration de l’environnement
```bash
cp .env.example .env
php artisan key:generate
```
Dans `.env` :
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jwt_auth_db
DB_USERNAME=root
DB_PASSWORD=
```

#### Migration
```bash
php artisan migrate
```
Créer d’autres migrations :
```bash
php artisan make:migration create_roles_table
```

#### CORS
Dans `config/cors.php` :
```php
'paths'            => ['api/*'],
'allowed_origins'  => ['http://localhost:4200'],
'allowed_methods'  => ['*'],
'allowed_headers'  => ['*'],
'exposed_headers'  => [],
'max_age'          => 0,
'supports_credentials' => true,
```

---

### Authentification JWT

#### Installation
```bash
composer require tymon/jwt-auth
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

#### Configuration
Dans `config/jwt.php` :
```php
'ttl'         => env('JWT_TTL', 60),
'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),
```
Ou dans `.env` :
```
JWT_TTL=120
JWT_REFRESH_TTL=20160
```

#### Guard
Dans `config/auth.php` :
```php
'guards' => [
  'api' => [
    'driver'   => 'jwt',
    'provider' => 'users',
  ],
],
```

---

### Permissions avec Spatie

#### Installation
```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

#### Modèle User
```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;
    // ...
}
```

#### Seeder rôles/permissions
```bash
php artisan make:seeder RolesAndPermissionsSeeder
```
```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Réinitialise le cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        $perms = [
          'view users','create users','edit users','delete users',
          'view roles','create roles','edit roles','delete roles',
        ];
        foreach ($perms as $p) Permission::create(['name'=>$p]);

        // Rôles
        $r = Role::create(['name'=>'Admin']);
        $r->givePermissionTo(Permission::all());

        $r = Role::create(['name'=>'Manager']);
        $r->givePermissionTo(['view users','edit users','view roles']);

        $r = Role::create(['name'=>'User']);
        $r->givePermissionTo(['view users']);
    }
}
```
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

---

### Contrôleurs et routes

#### AuthController
```bash
php artisan make:controller AuthController
```
```php
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $req)
    {
        $creds = $req->validate([
          'email'=>'required|email','password'=>'required',
        ]);
        if (!Auth::attempt($creds)) {
          return response()->json(['message'=>'Identifiants invalides'],401);
        }
        $user  = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
          'user'=>$user,
          'roles'=>$user->getRoleNames(),
          'permissions'=>$user->getAllPermissions()->pluck('name'),
        ])->cookie('auth_token',$token,60*24*7,'/',null,true,true);
    }

    public function logout(Request $req)
    {
        $req->user()->currentAccessToken()->delete();
        return response()->json(['message'=>'Déconnecté'])
               ->cookie('auth_token','',-1);
    }
}
```

#### RoleController / PermissionController / UserController
```bash
php artisan make:controller RoleController
php artisan make:controller PermissionController
php artisan make:controller UserController
```



```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();
        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles',
        ]);
        $role = Role::create(['name' => $request->name,'guard_name' => 'web']);
        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }
        return response()->json($role->load('permissions'), 201);
    }
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'sometimes|string|unique:roles,name,' . $role->id,
        ]);
        $role->update($request->only('name'));
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }
        return response()->json($role->load('permissions'));
    }
    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(null, 204);
    }
    public function assignPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);
        $role->syncPermissions($request->permissions);
        return response()->json($role->load('permissions'));
    }
}

```

#### PermissionController

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();
        return response()->json($permissions);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions',

        ]);
        $permission = Permission::create(['name' => $request->name,'guard_name' => 'web']);
        return response()->json($permission, 201);
    }
    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
        ]);
        $permission->update($request->only('name'));
        return response()->json($permission);
    }
    public function destroy(Permission $permission)
    {
        $permission->delete();
        return response()->json(null, 204);
    }
}
```

#### UserController

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->get();
        $roles = Role::all();
        return response()->json([
            'users' => $users,
            'roles' => $roles
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        if ($request->roles) {
            $user->syncRoles($request->roles);
        }
        return response()->json($user->load('roles'), 201);
    }
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
        ]);
        $data = $request->only(['name', 'email']);
        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }
        $user->update($data);
        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }
        return response()->json($user->load('roles'));
    }
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(null, 204);
    }
    public function assignRole(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);
        $user->syncRoles($request->roles);
        return response()->json($user->load('roles'));
    }
}

```



###

#### Custom Middleware(si nécessaire)

```bash
php artisan make:middleware CheckRole
```

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle($request, Closure $next, $role)
    {
        if (!auth()->user() || !auth()->user()->hasRole($role)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        
        return $next($request);
    }
}
```

#### API Routes

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Admin routes
    Route::middleware(['role:Admin'])->group(function () {
        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/assign-roles', [UserController::class, 'assignRole']);
        
        // Roles
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        Route::post('/roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions']);
        
        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::put('/permissions/{permission}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy']);
    });
});
```

---

## Frontend avec Angular

### Configuration du projet

#### Création du projet
```bash
npm install -g @angular/cli
ng new auth-frontend --routing --style=scss
cd auth-frontend
```

#### Ajout des packages requis
```bash
ng add @angular/material   # Optional UI framework
npm install primeng primeicons primeflex  # PrimeNG UI components
npm install @auth0/angular-jwt   # JWT helper library
npm install crypto-js     # For secure storage
npm install @types/crypto-js --save-dev
```

#### Structure du projet
```
src/app/
├── core/           # Services, interceptors, guards
├── auth/           # Auth components and logic
├── features/       # Feature modules (dashboard, admin, etc.)
├── shared/         # Shared components, directives
└── app.config.ts   # App configuration (Angular 17+)
```

---

### Services d’authentification

#### Auth Service

```bash
ng generate service services/auth
```

```typescript
import { Injectable } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { BehaviorSubject, Observable, throwError } from 'rxjs';
import { tap, catchError } from 'rxjs/operators';
import { Router } from '@angular/router';
import * as CryptoJS from 'crypto-js';

interface User {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
}

interface AuthResponse {
  user: User;
  roles: string[];
  permissions: string[];
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private apiUrl = 'http://localhost:8000/api';
  private currentUserSubject: BehaviorSubject<User | null>;
  public currentUser: Observable<User | null>;
  private secretKey = 'VOTRE_CLE_SECRETE_FORTE'; // Should be environment variable

  constructor(private http: HttpClient, private router: Router) {
    // Get user from sessionStorage
    const encryptedUser = sessionStorage.getItem('currentUser');
    let user: User | null = null;
    if (encryptedUser) {
      try { user = this.decryptData(encryptedUser); } catch (e) { user = null; }
    }
    this.currentUserSubject = new BehaviorSubject<User | null>(user);
    this.currentUser = this.currentUserSubject.asObservable();
  }

  private encryptData(data: any): string {
    return CryptoJS.AES.encrypt(JSON.stringify(data), this.secretKey).toString();
  }

  private decryptData(data: string): any {
    const bytes = CryptoJS.AES.decrypt(data, this.secretKey);
    return JSON.parse(bytes.toString(CryptoJS.enc.Utf8));
  }

  public get currentUserValue(): User | null {
    return this.currentUserSubject.value;
  }

  public isLoggedIn(): boolean {
    return !!this.currentUserValue;
  }

  public login(email: string, password: string): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(
      `${this.apiUrl}/login`,
      { email, password },
      { withCredentials: true }
    ).pipe(
      tap(response => {
        const user = {
          ...response.user,
          roles: response.roles,
          permissions: response.permissions
        };
        sessionStorage.setItem('currentUser', this.encryptData(user));
        this.currentUserSubject.next(user);
      })
    );
  }

  public logout(): void {
    this.http.post(`${this.apiUrl}/logout`, {}, { withCredentials: true })
      .subscribe({
        next: () => { this.clearAuthData(); },
        error: () => { this.clearAuthData(); }
      });
  }

  private clearAuthData(): void {
    sessionStorage.removeItem('currentUser');
    this.currentUserSubject.next(null);
    this.router.navigate(['/login']);
  }

  public checkAuthStatus(): Observable<User> {
    return this.http.get<User>(`${this.apiUrl}/user`, { withCredentials: true }).pipe(
      tap(user => {
        sessionStorage.setItem('currentUser', this.encryptData(user));
        this.currentUserSubject.next(user);
      }),
      catchError((error: HttpErrorResponse) => {
        if (error.status === 401) { this.clearAuthData(); }
        return throwError(() => error);
      })
    );
  }

  public hasRole(role: string): boolean {
    const user = this.currentUserValue;
    return user ? user.roles.includes(role) : false;
  }

  public hasPermission(permission: string): boolean {
    const user = this.currentUserValue;
    return user ? user.permissions.includes(permission) : false;
  }
}
```

#### App Configuration (Angular 17+)

```typescript
// src/app/app.config.ts
import { HTTP_INTERCEPTORS, provideHttpClient, withFetch, withInterceptors, withInterceptorsFromDi } from '@angular/common/http';
import { ApplicationConfig, importProvidersFrom } from '@angular/core';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import { provideRouter, withEnabledBlockingInitialNavigation, withInMemoryScrolling } from '@angular/router';
import Aura from '@primeng/themes/aura';
import { providePrimeNG } from 'primeng/config';
import { appRoutes } from './app.routes';
import { AuthInterceptor } from './app/services/auth.interceptor';
import { JwtModule } from '@auth0/angular-jwt';

export function tokenGetter() {
  return localStorage.getItem('token');
}

export const appConfig: ApplicationConfig = {
    providers: [
        provideRouter(appRoutes, withInMemoryScrolling({ anchorScrolling: 'enabled', scrollPositionRestoration: 'enabled' }), withEnabledBlockingInitialNavigation()),
        provideHttpClient(withFetch(), withInterceptorsFromDi()),
        provideAnimationsAsync(),
    
        { provide: HTTP_INTERCEPTORS, useClass: AuthInterceptor, multi: true },
        providePrimeNG({ theme: { preset: Aura, options: { darkModeSelector: '.app-dark' } } }),

        // Use importProvidersFrom for JwtModule
        importProvidersFrom(
            JwtModule.forRoot({
                config: {
                    tokenGetter: tokenGetter,
                    allowedDomains: ['localhost:8000'],
                    disallowedRoutes: ['localhost:8000/api/login', 'localhost:8000/api/register']
                }
            })
        )
    ]
};
```

---

### Intercepteurs HTTP

#### Auth Interceptor

```bash
ng generate interceptor interceptors/auth
```

```typescript
import { Injectable } from '@angular/core';
import {
  HttpRequest, HttpHandler, HttpEvent, HttpInterceptor, HttpErrorResponse
} from '@angular/common/http';
import { catchError, Observable, throwError } from 'rxjs';
import { AuthService } from './auth.service';

@Injectable()
export class AuthInterceptor implements HttpInterceptor {
  constructor(private authService: AuthService) {}

  intercept(request: HttpRequest<unknown>, next: HttpHandler): Observable<HttpEvent<unknown>> {
    // Add cookies for protected routes
    if (this.shouldIncludeCookies(request)) {
      const authRequest = request.clone({ withCredentials: true });
      return next.handle(authRequest).pipe(
        catchError((error: HttpErrorResponse) => {
          if (error.status === 401) {
            this.authService.logout();
          }
          return throwError(() => error);
        })
      );
    }
    return next.handle(request);
  }
  
  private shouldIncludeCookies(request: HttpRequest<any>): boolean {
    const publicRoutes = ['/login', '/register'];
    return !publicRoutes.some(route => request.url.includes(route));
  }
}
```

#### Error Interceptor (Optional)

```bash
ng generate interceptor interceptors/error
```

```typescript
import { Injectable } from '@angular/core';
import { HttpEvent, HttpInterceptor, HttpHandler, HttpRequest, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { Router } from '@angular/router';

@Injectable()
export class ErrorInterceptor implements HttpInterceptor {
  constructor(private router: Router) {}
  
  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    return next.handle(req).pipe(
      catchError((error: HttpErrorResponse) => {
        if (error.status === 403) {
          this.router.navigate(['/unauthorized']);
        }
        // Handle other errors, show notifications, etc.
        return throwError(() => error);
      })
    );
  }
}
```

---

### Gardes et directives

#### Auth Guard

```bash
ng generate guard guards/auth
```

```typescript
import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { Observable } from 'rxjs';
import { AuthService } from './auth.service';

@Injectable({
  providedIn: 'root'
})
export class AuthGuard implements CanActivate {
  constructor(private authService: AuthService, private router: Router) { }
  
  canActivate(
    next: ActivatedRouteSnapshot,
    state: RouterStateSnapshot): Observable<boolean> | Promise<boolean> | boolean {
    if (this.authService.isLoggedIn()) {
      return true;
    }
    
    this.router.navigate(['/auth/login'], { queryParams: { returnUrl: state.url } });
    return false;
  }
}
```

#### Role Guard

```bash
ng generate guard guards/role
```

```typescript
import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { AuthService } from './auth.service';

@Injectable({
  providedIn: 'root'
})
export class RoleGuard implements CanActivate {
  constructor(private authService: AuthService, private router: Router) { }
  
  canActivate(
    next: ActivatedRouteSnapshot,
    state: RouterStateSnapshot): boolean {
    const requiredRoles = next.data['roles'] as Array<string>;
    
    if (this.authService.isLoggedIn() && 
        requiredRoles.some(role => this.authService.hasRole(role))) {
      return true;
    }
    
    this.router.navigate(['/unauthorized']);
    return false;
  }
}
```

#### Has Role Directive

```bash
ng generate directive directives/has-role
```

```typescript
import { Directive, Input, TemplateRef, ViewContainerRef } from '@angular/core';
import { AuthService } from './auth.service';

@Directive({
  selector: '[appHasRole]'
})
export class HasRoleDirective {
  @Input() set appHasRole(role: string) {
    if (this.authService.hasRole(role)) {
      this.viewContainer.createEmbeddedView(this.templateRef);
    } else {
      this.viewContainer.clear();
    }
  }
  
  constructor(
    private templateRef: TemplateRef<any>,
    private viewContainer: ViewContainerRef,
    private authService: AuthService
  ) { }
}
```

#### Has Permission Directive

```bash
ng generate directive directives/has-permission
```

```typescript
import { Directive, Input, TemplateRef, ViewContainerRef } from '@angular/core';
import { AuthService } from './auth.service';

/**
 * Structural directive to show/hide elements based on user permissions.
 * Usage: <div *appHasPermission="'PERMISSION_NAME'"> ... </div>
 */
@Directive({
  selector: '[appHasPermission]'
})
export class HasPermissionDirective {
  /**
   * Sets whether to show or hide the element based on the permission.
   */
  @Input() set appHasPermission(permission: string) {
    if (this.authService.hasPermission(permission)) {
      // Show content if permission exists
      this.viewContainer.createEmbeddedView(this.templateRef);
    } else {
      // Otherwise hide the content
      this.viewContainer.clear();
    }
  }

  /**
   * @param templateRef Reference to the HTML template
   * @param viewContainer Container to insert/remove the template from DOM
   * @param authService Auth service to check permissions
   */
  constructor(
    private templateRef: TemplateRef<any>,
    private viewContainer: ViewContainerRef,
    private authService: AuthService
  ) { }
}
```

---

## Améliorations de sécurité

### Crypto Service

```bash
ng generate service services/crypto
```

```typescript
import { Injectable } from '@angular/core';
import * as CryptoJS from 'crypto-js';

@Injectable({
  providedIn: 'root'
})
export class CryptoService {
  private key = 'SECURE_SECRET_KEY'; // Should be environment variable

  encrypt(value: string): string {
    return CryptoJS.AES.encrypt(value, this.key).toString();
  }

  decrypt(value: string): string {
    const bytes = CryptoJS.AES.decrypt(value, this.key);
    return bytes.toString(CryptoJS.enc.Utf8);
  }

  hash(value: string): string {
    return CryptoJS.SHA256(value).toString();
  }
}
```

### Bonnes pratiques de sécurité

1. **XSS Protection**
   - Use Angular's built-in sanitization
   - Avoid `[innerHTML]` with untrusted content
   - Use Content-Security-Policy headers

2. **CSRF Protection**
   - Laravel provides CSRF protection out of the box
   - For APIs, use SameSite cookies

3. **Secure Storage**
   - Use HttpOnly cookies for tokens when possible
   - Encrypt sensitive data in browser storage
   - Consider using sessionStorage for shorter-lived sessions

4. **Token Management**
   - Implement token refresh strategies
   - Blacklist tokens on logout
   - Set appropriate expiration times

---

## Interface utilisateur ,  avec Angular PrimeNG 

## Interface utilisateur avec Angular PrimeNG

PrimeNG fournit un ensemble complet de composants UI permettant de créer des interfaces modernes et professionnelles pour notre système d'authentification et de gestion des permissions.

### Configuration de PrimeNG

Tout d'abord, assurons-nous que PrimeNG est correctement configuré dans notre projet Angular :

```bash
# Installation des dépendances PrimeNG
npm install primeng primeicons primeflex
```

Dans `angular.json`, ajoutez les styles :

```json
"styles": [
  "node_modules/primeng/resources/themes/lara-light-blue/theme.css",
  "node_modules/primeng/resources/primeng.min.css",
  "node_modules/primeicons/primeicons.css",
  "node_modules/primeflex/primeflex.css",
  "src/styles.scss"
]
```

Créez un module partagé pour importer les composants PrimeNG :

```bash
ng generate module shared/primeng
```

```typescript
// src/app/shared/primeng.module.ts
import { NgModule } from '@angular/core';

// PrimeNG Components
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { PasswordModule } from 'primeng/password';
import { CheckboxModule } from 'primeng/checkbox';
import { CardModule } from 'primeng/card';
import { ToastModule } from 'primeng/toast';
import { TableModule } from 'primeng/table';
import { DialogModule } from 'primeng/dialog';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { DropdownModule } from 'primeng/dropdown';
import { MultiSelectModule } from 'primeng/multiselect';
import { MenuModule } from 'primeng/menu';
import { MenubarModule } from 'primeng/menubar';
import { SidebarModule } from 'primeng/sidebar';
import { DividerModule } from 'primeng/divider';
import { PanelModule } from 'primeng/panel';
import { ToolbarModule } from 'primeng/toolbar';
import { TooltipModule } from 'primeng/tooltip';
import { MessageModule } from 'primeng/message';
import { ChipModule } from 'primeng/chip';
import { TagModule } from 'primeng/tag';

@NgModule({
  exports: [
    ButtonModule,
    InputTextModule,
    PasswordModule,
    CheckboxModule,
    CardModule,
    ToastModule,
    TableModule,
    DialogModule,
    ConfirmDialogModule,
    DropdownModule,
    MultiSelectModule,
    MenuModule,
    MenubarModule,
    SidebarModule,
    DividerModule,
    PanelModule,
    ToolbarModule,
    TooltipModule,
    MessageModule,
    ChipModule,
    TagModule
  ]
})
export class PrimeNGModule { }
```

### Interface de login

Créons un login professionnel avec PrimeNG :

```bash
ng generate component auth/login
```

```typescript
// src/app/auth/login/login.component.ts
import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { MessageService } from 'primeng/api';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss'],
  providers: [MessageService]
})
export class LoginComponent implements OnInit {
  loginForm: FormGroup;
  loading = false;
  returnUrl: string;
  
  constructor(
    private fb: FormBuilder,
    private route: ActivatedRoute,
    private router: Router,
    private authService: AuthService,
    private messageService: MessageService
  ) {
    // Rediriger si déjà connecté
    if (this.authService.isLoggedIn()) {
      this.router.navigate(['/dashboard']);
    }
  }
  
  ngOnInit() {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', Validators.required],
      rememberMe: [false]
    });
    
    // Récupérer l'URL de retour des query params ou définir la valeur par défaut
    this.returnUrl = this.route.snapshot.queryParams['returnUrl'] || '/dashboard';
  }
  
  get f() { return this.loginForm.controls; }
  
  onSubmit() {
    // Valider le formulaire
    if (this.loginForm.invalid) {
      Object.keys(this.loginForm.controls).forEach(key => {
        const control = this.loginForm.get(key);
        control.markAsTouched();
      });
      return;
    }
    
    this.loading = true;
    
    this.authService.login(
      this.f.email.value,
      this.f.password.value
    ).subscribe({
      next: () => {
        this.loading = false;
        this.router.navigate([this.returnUrl]);
      },
      error: (error) => {
        this.loading = false;
        this.messageService.add({ 
          severity: 'error', 
          summary: 'Erreur de connexion', 
          detail: error.error?.message || 'Identifiants incorrects' 
        });
      }
    });
  }
}
```

```html
<!-- src/app/auth/login/login.component.html -->
<div class="login-container">
  <div class="grid flex justify-content-center">
    <div class="col-12 sm:col-8 md:col-6 lg:col-4">
      <p-card styleClass="shadow-5 mt-6">
        <ng-template pTemplate="header">
          <div class="flex justify-content-center pt-5">
            <img src="assets/images/logo.png" alt="Logo" height="60">
          </div>
          <h2 class="text-center mb-0">Connexion</h2>
        </ng-template>
        
        <p-toast position="top-right"></p-toast>
        
        <form [formGroup]="loginForm" (ngSubmit)="onSubmit()">
          <div class="p-fluid">
            <div class="field">
              <label for="email">Email</label>
              <span class="p-input-icon-left w-full">
                <i class="pi pi-user"></i>
                <input 
                  id="email" 
                  type="email" 
                  pInputText 
                  formControlName="email" 
                  placeholder="exemple@domaine.com"
                >
              </span>
              <small 
                *ngIf="f.email.touched && f.email.errors?.required" 
                class="p-error"
              >Email requis</small>
              <small 
                *ngIf="f.email.touched && f.email.errors?.email" 
                class="p-error"
              >Format d'email invalide</small>
            </div>
            
            <div class="field">
              <label for="password">Mot de passe</label>
              <span class="p-input-icon-left w-full">
                <i class="pi pi-lock"></i>
                <p-password 
                  id="password" 
                  formControlName="password" 
                  [toggleMask]="true"
                  [feedback]="false"
                  styleClass="w-full"
                  placeholder="Votre mot de passe"
                ></p-password>
              </span>
              <small 
                *ngIf="f.password.touched && f.password.errors?.required" 
                class="p-error"
              >Mot de passe requis</small>
            </div>
            
            <div class="field-checkbox">
              <p-checkbox 
                id="rememberMe" 
                formControlName="rememberMe" 
                [binary]="true"
              ></p-checkbox>
              <label for="rememberMe">Se souvenir de moi</label>
            </div>
            
            <div class="field">
              <p-button 
                type="submit" 
                [loading]="loading" 
                label="Se connecter"
                icon="pi pi-sign-in" 
                styleClass="w-full"
              ></p-button>
            </div>
          </div>
        </form>
        
        <div class="text-center mt-3">
          <a routerLink="/auth/forgot-password" class="text-primary no-underline">Mot de passe oublié?</a>
        </div>
      </p-card>
      
      <div class="mt-3 text-center">
        <p>Pas encore de compte? <a routerLink="/auth/register" class="text-primary font-medium">S'inscrire</a></p>
      </div>
    </div>
  </div>
</div>
```

```scss
/* src/app/auth/login/login.component.scss */
.login-container {
  min-height: 100vh;
  background: linear-gradient(to right, #667eea, #764ba2);
  padding: 1rem;
  
  .p-card {
    border-radius: 8px;
  }
}
```

### Composant de configuration des routes

```bash
ng generate module app-routing
```

```typescript
// src/app/app-routing.module.ts
import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from './core/guards/auth.guard';
import { RoleGuard } from './core/guards/role.guard';
import { LoginComponent } from './auth/login/login.component';
import { DashboardComponent } from './features/dashboard/dashboard.component';
import { UnauthorizedComponent } from './shared/components/unauthorized/unauthorized.component';

const routes: Routes = [
  { path: '', redirectTo: '/dashboard', pathMatch: 'full' },
  { path: 'auth/login', component: LoginComponent },
  { 
    path: 'dashboard', 
    component: DashboardComponent, 
    canActivate: [AuthGuard] 
  },
  { 
    path: 'admin', 
    loadChildren: () => import('./features/admin/admin.module').then(m => m.AdminModule),
    canActivate: [AuthGuard, RoleGuard],
    data: { roles: ['Admin'] }
  },
  { path: 'unauthorized', component: UnauthorizedComponent },
  { path: '**', redirectTo: '/dashboard' }
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule { }
```
z

### Interface Permissions

Creons le service de permissions :
```bash
ng generate service core/services/permission.service
```
```typescript
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface Permission {
  id: number;
  name: string;
}

@Injectable({
  providedIn: 'root'
})
export class PermissionService {
  private apiUrl = 'http://localhost:8000/api/permissions';

  constructor(private http: HttpClient) {}

  getPermissions(): Observable<Permission[]> {
    return this.http.get<Permission[]>(this.apiUrl, { withCredentials: true });
  }

  createPermission(permission: { name: string }): Observable<Permission> {
    return this.http.post<Permission>(this.apiUrl, permission, { withCredentials: true });
  }

  updatePermission(id: number, permission: { name: string }): Observable<Permission> {
    return this.http.put<Permission>(`${this.apiUrl}/${id}`, permission, { withCredentials: true });
  }

  deletePermission(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`, { withCredentials: true });
  }
}
```
### la liste des permissions avec PrimeNG
```bash
ng generate component features/admin/permissions/permission-list
```
```typescript
// src/app/features/admin/permissions/permission-list/permission-list.component.ts

import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ConfirmationService, MessageService } from 'primeng/api';
import { PermissionService } from '../../../../core/services/permission.service';
import { Permission } from '../../../../core/models/permission.model';

// PrimeNG Imports
import { TableModule } from 'primeng/table';


@Component({
  selector: 'app-permission-list',
  templateUrl: './permission-list.component.html',
  providers: [ConfirmationService, MessageService],
  standalone: true,
  imports: [
 
  ]
})
export class PermissionListComponent implements OnInit {
  permissions: Permission[] = [];
  loading: boolean = true;

  constructor(
    private permissionService: PermissionService,
    private router: Router,
    private confirmationService: ConfirmationService,
    private messageService: MessageService
  ) { }

  ngOnInit(): void {
    this.loadPermissions();
  }

  loadPermissions(): void {
    this.loading = true;
    this.permissionService.getPermissions().subscribe({
      next: (data) => {
        this.permissions = data;
        this.loading = false;
      },
      error: (error) => {
        this.messageService.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de charger les permissions'
        });
        this.loading = false;
      }
    });
  }

  createPermission(): void {
    this.router.navigate(['/profile/permissions/create']);
  }

  editPermission(permission: Permission): void {
    this.router.navigate(['/profile/permissions/edit', permission.id]);
  }

  confirmDelete(permission: Permission): void {
    this.confirmationService.confirm({
      message: `Êtes-vous sûr de vouloir supprimer la permission "${permission.name}" ?`,
      header: 'Confirmation de suppression',
      icon: 'pi pi-exclamation-triangle',
      accept: () => this.deletePermission(permission.id)
    });
  }

  deletePermission(id: number): void {
    this.permissionService.deletePermission(id).subscribe({
      next: () => {
        this.messageService.add({
          severity: 'success',
          summary: 'Succès',
          detail: 'Permission supprimée avec succès'
        });
        this.loadPermissions();
      },
      error: (error) => {
        this.messageService.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de supprimer la permission'
        });
      }
    });
  }
}

```
le html de la liste des permissions

```html
<!-- src/app/modules/permission/components/permission-list/permission-list.component.html -->
<div class="card">
  <p-toast></p-toast>
  <p-confirmDialog [style]="{width: '450px'}"></p-confirmDialog>

  <p-toolbar styleClass="mb-4 gap-2">
    <ng-template pTemplate="left">
      <h2>Gestion des permissions</h2>
    </ng-template>
    <ng-template pTemplate="right">
      <button pButton pRipple label="Nouvelle permission" icon="pi pi-plus" class="p-button-success mr-2" (click)="createPermission()"></button>
    </ng-template>
  </p-toolbar>

  <p-table 
    [value]="permissions" 
    [paginator]="true" 
    [rows]="10" 
    [rowsPerPageOptions]="[5, 10, 25]"
    [loading]="loading" 
    styleClass="p-datatable-gridlines p-datatable-responsive"
    [responsive]="true">
    
    <ng-template pTemplate="header">
      <tr>
        <th pSortableColumn="id">ID <p-sortIcon field="id"></p-sortIcon></th>
        <th pSortableColumn="name">Nom <p-sortIcon field="name"></p-sortIcon></th>
        <th style="min-width: 8rem">Actions</th>
      </tr>
    </ng-template>

    <ng-template pTemplate="body" let-permission>
      <tr>
        <td><span class="p-column-title">ID</span>{{ permission.id }}</td>
        <td><span class="p-column-title">Nom</span>{{ permission.name }}</td>
        <td>
          <div class="flex gap-2">
            <button pButton pRipple icon="pi pi-pencil" class="p-button-rounded p-button-success p-button-sm" (click)="editPermission(permission)" pTooltip="Éditer"></button>
            <button pButton pRipple icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-sm" (click)="confirmDelete(permission)" pTooltip="Supprimer"></button>
          </div>
        </td>
      </tr>
    </ng-template>

    <ng-template pTemplate="emptymessage">
      <tr>
        <td colspan="3" class="text-center">Aucune permission trouvée.</td>
      </tr>
    </ng-template>
  </p-table>
</div>
```

### le form de permissions avec PrimeNG

```bash
ng generate component features/admin/permissions/permission-form
```
```typescript
// src/app/modules/permission/components/permission-form/permission-form.component.ts
import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { MessageService } from 'primeng/api';
import { PermissionService } from '../../../../services/permission.service';
import { Permission } from '../../../../model/permission.model';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';

// PrimeNG Imports
import { TableModule } from 'primeng/table';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { CardModule } from 'primeng/card';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { DialogModule } from 'primeng/dialog';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { MessagesModule } from 'primeng/messages';
import { MessageModule } from 'primeng/message';
import { ToolbarModule } from 'primeng/toolbar';

@Component({
  selector: 'app-permission-form',
  templateUrl: './permission-form.component.html',
  providers: [MessageService],
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    TableModule,
    ButtonModule,
    InputTextModule,
    CardModule,
    ToastModule,
    ConfirmDialogModule,
    DialogModule,
    ProgressSpinnerModule,
    MessagesModule,
    MessageModule,
    ToolbarModule
  ]
})
export class PermissionFormComponent implements OnInit {
  permissionForm!: FormGroup;
  isEditMode: boolean = false;
  permissionId: number | null = null;
  submitting: boolean = false;
  loading: boolean = false;

  constructor(
    private fb: FormBuilder,
    private permissionService: PermissionService,
    private route: ActivatedRoute,
    private router: Router,
    private messageService: MessageService
  ) { }

  ngOnInit(): void {
    this.initForm();
    
    this.route.params.subscribe(params => {
      if (params['id']) {
        this.isEditMode = true;
        this.permissionId = +params['id'];
        this.loadPermission(this.permissionId);
      }
    });
  }

  initForm(): void {
    this.permissionForm = this.fb.group({
      name: ['', [Validators.required]]
    });
  }

  loadPermission(id: number): void {
    this.loading = true;
    this.permissionService.getPermissions().subscribe({
      next: (data) => {
        const permission = data.find((p: Permission) => p.id === id);
        
        if (permission) {
          this.permissionForm.patchValue({
            name: permission.name
          });
        } else {
          this.messageService.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Permission non trouvée'
          });
          setTimeout(() => this.router.navigate(['/profile/permissions']), 1500);
        }
        this.loading = false;
      },
      error: (error) => {
        this.messageService.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de charger la permission'
        });
        this.loading = false;
      }
    });
  }

  onSubmit(): void {
    if (this.permissionForm.invalid) {
      this.permissionForm.markAllAsTouched();
      this.messageService.add({
        severity: 'error',
        summary: 'Erreur',
        detail: 'Veuillez corriger les erreurs dans le formulaire'
      });
      return;
    }

    this.submitting = true;
    const formData = this.permissionForm.value;

    if (this.isEditMode && this.permissionId) {
      // Mise à jour de la permission
      this.permissionService.updatePermission(this.permissionId, formData).subscribe({
        next: () => {
          this.messageService.add({
            severity: 'success',
            summary: 'Succès',
            detail: 'Permission mise à jour avec succès'
          });
          setTimeout(() => this.router.navigate(['/profile/permissions']), 1500);
        },
        error: (error) => {
          this.messageService.add({
            severity: 'error',
            summary: 'Erreur',
            detail: error.error?.message || 'Erreur lors de la mise à jour de la permission'
          });
          this.submitting = false;
        }
      });
    } else {
      // Création d'une nouvelle permission
      this.permissionService.createPermission(formData).subscribe({
        next: () => {
          this.messageService.add({
            severity: 'success',
            summary: 'Succès',
            detail: 'Permission créée avec succès'
          });
          setTimeout(() => this.router.navigate(['/profile/permissions']), 1500);
        },
        error: (error) => {
          this.messageService.add({
            severity: 'error',
            summary: 'Erreur',
            detail: error.error?.message || 'Erreur lors de la création de la permission'
          });
          this.submitting = false;
        }
      });
    }
  }

  cancel(): void {
    this.router.navigate(['/profile/permissions']);
  }
}
```
le html du composant form

```html
<!-- src/app/modules/permission/components/permission-form/permission-form.component.html -->
 <div class="card">
    <p-toast></p-toast>
    
    <div class="card-header">
      <h2>{{ isEditMode ? 'Modifier une permission' : 'Créer une permission' }}</h2>
    </div>
  
    <div *ngIf="loading" class="flex justify-content-center">
      <p-progressSpinner></p-progressSpinner>
    </div>
  
    <div *ngIf="!loading" class="p-fluid">
      <form [formGroup]="permissionForm" (ngSubmit)="onSubmit()">
        <div class="field mb-4">
          <label for="name" class="font-bold">Nom de la permission</label>
          <input id="name" type="text" pInputText formControlName="name" class="w-full" />
          <small *ngIf="permissionForm.get('name')?.invalid && permissionForm.get('name')?.touched" class="p-error">
            Le nom de la permission est requis
          </small>
        </div>
  
        <div class="flex justify-content-end mt-4 gap-2">
          <button 
            pButton 
            pRipple 
            type="button" 
            label="Annuler" 
            class="p-button-secondary" 
            (click)="cancel()" 
            [disabled]="submitting">
          </button>
          <button 
            pButton 
            pRipple 
            type="submit" 
            label="Enregistrer" 
            class="p-button-primary" 
            [disabled]="submitting || loading">
          </button>
        </div>
      </form>
    </div>
    
  </div>
```



### Interface de gestion des rôles

le service de gestion des rôles est le suivant :
```bash
ng generate service core/services/role
```
```typescript

import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface Role {
  id: number;
  name: string;
  permissions: string[];
}

@Injectable({
  providedIn: 'root'
})
export class RoleService {
  private apiUrl = 'http://localhost:8000/api/roles';
  constructor(private http: HttpClient) { }

  getRoles(): Observable<any> {
    return this.http.get<any>(this.apiUrl);
  }

  createRole(role: { name: string, permissions: string[] }): Observable<Role> {
    return this.http.post<Role>(this.apiUrl, role);
  }

  updateRole(id: number, role: { name: string, permissions: string[] }): Observable<Role> {
    return this.http.put<Role>(`${this.apiUrl}/${id}`, role);
  }

  deleteRole(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }
  
  assignPermissions(roleId: number, permissions: string[]): Observable<Role> {
    return this.http.post<Role>(`${this.apiUrl}/${roleId}/assign-permissions`, { permissions });
  }
}
```
Implémentons maintenant le composant de liste des rôles :
la commande suivante :

```bash
ng generate component features/admin/roles/role-list
```

```typescript
// src/app/features/admin/roles/role-list/role-list.component.ts
// src/app/modules/role/components/role-list/role-list.component.ts
import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ConfirmationService, MessageService } from 'primeng/api';
import { RoleService } from '../../../services/role.service';
import { Role } from '../../../model/role.model';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
// PrimeNG Imports
import { TableModule } from 'primeng/table';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { CardModule } from 'primeng/card';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { DialogModule } from 'primeng/dialog';
import { InputSwitchModule } from 'primeng/inputswitch';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { MessagesModule } from 'primeng/messages';
import { MessageModule } from 'primeng/message';
import { DividerModule } from 'primeng/divider';
import { ToolbarModule } from 'primeng/toolbar';
import { MultiSelectModule } from 'primeng/multiselect';
import { TagModule } from 'primeng/tag';

@Component({
  selector: 'app-role-list',
  templateUrl: './role-list.component.html',
  providers: [ConfirmationService, MessageService],
  standalone: true,
  imports: [
    ProgressSpinnerModule,
    TableModule,
    ButtonModule,
    DialogModule,
    InputTextModule,
    MultiSelectModule,
    ConfirmDialogModule,
    CardModule,
    TagModule,
    ToastModule,
    MessagesModule,
    MessageModule,
    DividerModule,
    ToolbarModule,
    InputSwitchModule,
    ProgressSpinnerModule,
    ReactiveFormsModule,
    CommonModule,
    FormsModule
  ]
})
export class RoleListComponent implements OnInit {
  roles: Role[] = [];
  filteredRoles: Role[] = [];
  loading: boolean = true;
  searchTerm: string = '';

  constructor(
    private roleService: RoleService,
    private router: Router,
    private confirmationService: ConfirmationService,
    private messageService: MessageService
  ) { }

  ngOnInit(): void {
    this.loadRoles();
  }

  onSearch(event: Event): void {
    const query = (event.target as HTMLInputElement).value.toLowerCase();
    this.filteredRoles = this.roles.filter(role => 
      role.name.toLowerCase().includes(query) ||
      role.id.toString().includes(query)
    );
  }

  loadRoles(): void {
    this.loading = true;
    this.roleService.getRoles().subscribe({
      next: (data) => {
        this.roles = data.roles;
        this.filteredRoles = [...this.roles];
        this.loading = false;
      },
      error: (error) => {
        this.messageService.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de charger les rôles'
        });
        this.loading = false;
      }
    });
  }

  createRole(): void {
    this.router.navigate(['profile/roles/create']);
  }

  editRole(role: Role): void {
    this.router.navigate(['profile/roles/edit', role.id]);
  }

  confirmDelete(role: Role): void {
    this.confirmationService.confirm({
      message: `Êtes-vous sûr de vouloir supprimer le rôle "${role.name}" ?`,
      header: 'Confirmation de suppression',
      icon: 'pi pi-exclamation-triangle',
      accept: () => this.deleteRole(role.id)
    });
  }

  deleteRole(id: number): void {
    this.roleService.deleteRole(id).subscribe({
      next: () => {
        this.messageService.add({
          severity: 'success',
          summary: 'Succès',
          detail: 'Rôle supprimé avec succès'
        });
        this.loadRoles();
      },
      error: (error) => {
        this.messageService.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de supprimer le rôle'
        });
      }
    });
  }
}
```
le html est le suivant :

```html

<div class="card" >
  <p-toast></p-toast>
  <p-confirmDialog [style]="{width: '450px'}"></p-confirmDialog>

  <p-toolbar styleClass="mb-4 gap-2">
    <ng-template pTemplate="left">
      <h2>Gestion des rôles</h2>
      <span class="p-input-icon-left ml-2">
        <i class="pi pi-search"></i>
        <input pInputText type="text" [(ngModel)]="searchTerm" (input)="onSearch($event)" placeholder="Rechercher un rôle..." />
      </span>
    </ng-template>
    <ng-template pTemplate="right">
      <button pButton pRipple label="Nouveau rôle" icon="pi pi-plus" class="p-button-success mr-2" (click)="createRole()"></button>
    </ng-template>
  </p-toolbar>

  <p-table 
    [value]="roles" 
    [paginator]="true" 
    [rows]="10" 
    [rowsPerPageOptions]="[5, 10, 25]"
    [loading]="loading" 
    styleClass="p-datatable-gridlines p-datatable-responsive"
    [responsive]="true">
    
    <ng-template pTemplate="header">
      <tr>
        <th pSortableColumn="id">ID <p-sortIcon field="id"></p-sortIcon></th>
        <th pSortableColumn="name">Nom <p-sortIcon field="name"></p-sortIcon></th>
        <th style="min-width: 8rem">Actions</th>
      </tr>
    </ng-template>

    <ng-template pTemplate="body" let-role>
      <tr>
        <td><span class="p-column-title">ID</span>{{ role.id }}</td>
        <td>{{ role.name }}</td>
       
        <td>
          <div class="flex gap-2">
            <button pButton pRipple icon="pi pi-pencil" class="p-button-rounded p-button-success p-button-sm" (click)="editRole(role)" pTooltip="Éditer"></button>
            <button pButton pRipple icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-sm" (click)="confirmDelete(role)" pTooltip="Supprimer"></button>
          </div>
        </td>
      </tr>
    </ng-template>

    <ng-template pTemplate="emptymessage">
      <tr>
        <td colspan="3" class="text-center">Aucun rôle trouvé.</td>
      </tr>
    </ng-template>
  </p-table>
</div>
```

le form de role est le suivant :

```typescript
// src/app/modules/role/components/role-form/role-form.component.ts
import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { MessageService } from 'primeng/api';
import { RoleService } from '../../../../services/role.service';
import { PermissionService } from '../../../../services/permission.service';
import { Role } from '../../../../model/role.model';
import { Permission } from '../../../../model/permission.model';
import { forkJoin } from 'rxjs';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';

// PrimeNG Imports
import { TableModule } from 'primeng/table';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { CardModule } from 'primeng/card';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { DialogModule } from 'primeng/dialog';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { MessagesModule } from 'primeng/messages';
import { MessageModule } from 'primeng/message';
import { ToolbarModule } from 'primeng/toolbar';
import { InputSwitchModule } from 'primeng/inputswitch';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-role-form',
  templateUrl: './role-form.component.html',
  providers: [MessageService],
  standalone: true,
  imports: [   
    FormsModule,
    CommonModule,
    ReactiveFormsModule,
    TableModule,
    ButtonModule,
    InputTextModule,
    CardModule,
    ToastModule,
    ConfirmDialogModule,
    DialogModule,
    ProgressSpinnerModule,
    MessagesModule,
    MessageModule,
    ToolbarModule,
    InputSwitchModule
  ]
})
export class RoleFormComponent implements OnInit {
  roleForm!: FormGroup;
  isEditMode: boolean = false;
  roleId: number | null = null;
  permissions: Permission[] = [];
  rolePermissions: { [key: number]: boolean } = {};
  submitting: boolean = false;
  loading: boolean = false;

  constructor(
    private fb: FormBuilder,
    private roleService: RoleService,
    private permissionService: PermissionService,
    private route: ActivatedRoute,
    private router: Router,
    private messageService: MessageService
  ) { }

  ngOnInit(): void {
    this.initForm();
    this.loadPermissions();
    
    this.route.params.subscribe(params => {
      if (params['id']) {
        this.isEditMode = true;
        this.roleId = +params['id'];
        this.loadRole(this.roleId);
      }
    });
  }

  initForm(): void {
    this.roleForm = this.fb.group({
      name: ['', [Validators.required]]
    });
  }

  loadPermissions(): void {
    this.loading = true;
    this.permissionService.getPermissions().subscribe({
      next: (data) => {
        this.permissions = data;
        this.loading = false;
      },
      error: (error) => {
        this.messageService.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de charger les permissions'
        });
        this.loading = false;
      }
    });
  }

  loadRole(id: number): void {
    this.loading = true;
    this.roleService.getRoles().subscribe({
      next: (data) => {
        const roles = data.roles as Role[];
        const role = roles.find(r => r.id === id);
        
        if (role) {
          this.roleForm.patchValue({ name: role.name });

          // Initialiser les permissions du rôle
          this.rolePermissions = {};
          role.permissions.forEach(permission => {
            this.rolePermissions[permission.id] = true;
          });
        }
        this.loading = false;
      },
      error: (error) => {
        this.messageService.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de charger les informations du rôle'
        });
        this.loading = false;
      }
    });
  }

  onPermissionToggle(permissionId: number, checked: boolean): void {
    this.rolePermissions[permissionId] = checked;
  }

  onSubmit(): void {
    if (this.roleForm.invalid) {
      this.roleForm.markAllAsTouched();
      this.messageService.add({
        severity: 'error',
        summary: 'Erreur',
        detail: 'Veuillez corriger les erreurs dans le formulaire'
      });
      return;
    }

    this.submitting = true;
    const formData = this.roleForm.value;
    const selectedPermissionNames = Object.entries(this.rolePermissions)
      .filter(([_, isSelected]) => isSelected)
      .map(([id]) => this.permissions.find(p => p.id === Number(id))?.name || '');

    if (this.isEditMode && this.roleId) {
      // Mise à jour du rôle
      const updateRole = this.roleService.updateRole(this.roleId, {
        name: formData.name,
        permissions: selectedPermissionNames
      });

      const assignPermissions = this.roleService.assignPermissions(this.roleId, selectedPermissionNames);

      forkJoin([updateRole, assignPermissions]).subscribe({
        next: () => {
          this.messageService.add({
            severity: 'success',
            summary: 'Succès',
            detail: 'Rôle mis à jour avec succès'
          });
          setTimeout(() => this.router.navigate(['/profile/roles']), 1500);
        },
        error: (error) => {
          this.messageService.add({
            severity: 'error',
            summary: 'Erreur',
            detail: error.error?.message || 'Erreur lors de la mise à jour du rôle'
          });
          this.submitting = false;
        }
      });
    } else {
      // Création d'un nouveau rôle
      this.roleService.createRole({
        name: formData.name,
        permissions: selectedPermissionNames
      }).subscribe({
        next: (role) => {
          this.messageService.add({
            severity: 'success',
            summary: 'Succès',
            detail: 'Rôle créé avec succès'
          });
          setTimeout(() => this.router.navigate(['/profile/roles']), 1500);
        },
        error: (error) => {
          this.messageService.add({
            severity: 'error',
            summary: 'Erreur',
            detail: error.error?.message || 'Erreur lors de la création du rôle'
          });
          this.submitting = false;
        }
      });
    }
  }

  cancel(): void {
    this.router.navigate(['/profile/roles']);
  }
}
```
le html est le suivant :

```html
<!-- src/app/modules/role/components/role-form/role-form.component.html -->

<div class="card">
    <p-toast></p-toast>
    
    <div class="card-header">
      <h2>{{ isEditMode ? 'Modifier un rôle' : 'Créer un rôle' }}</h2>
    </div>
  
    <div *ngIf="loading" class="flex justify-content-center">
      <p-progressSpinner></p-progressSpinner>
    </div>
  
    <div *ngIf="!loading" class="p-fluid">
      <form [formGroup]="roleForm" (ngSubmit)="onSubmit()">
        <div class="field mb-4">
          <label for="name" class="font-bold">Nom du rôle</label>
          <input id="name" type="text" pInputText formControlName="name" class="w-full" />
          <small *ngIf="roleForm.get('name')?.invalid && roleForm.get('name')?.touched" class="p-error">
            Le nom du rôle est requis
          </small>
        </div>
  
        <div class="field">
          <label class="font-bold d-block mb-3">Permissions</label>
          <p-card>
            <div class="grid">
              <div *ngFor="let permission of permissions" class="col-12 md:col-6 lg:col-4 field-checkbox">
                <div class="flex align-items-center">
                  <p-inputSwitch 
                    [ngModel]="rolePermissions[permission.id] || false" 
                    (ngModelChange)="onPermissionToggle(permission.id, $event)" 
                    [ngModelOptions]="{standalone: true}">
                  </p-inputSwitch>
                  <label class="ml-2">{{ permission.name }}</label>
                </div>
              </div>
            </div>
            <div *ngIf="permissions.length === 0" class="text-center p-3">
              <p>Aucune permission disponible.</p>
            </div>
          </p-card>
        </div>
  
        <div class="flex justify-content-end mt-4 gap-2">
          <button 
            pButton 
            pRipple 
            type="button" 
            label="Annuler" 
            class="p-button-secondary" 
            (click)="cancel()" 
            [disabled]="submitting">
          </button>
          <button 
            pButton 
            pRipple 
            type="submit" 
            label="Enregistrer" 
            class="p-button-primary" 
            [disabled]="submitting || loading">
          </button>
        </div>
      </form>
    </div>
  </div>

```


### Interface de gestion des utilisateurs

creons le service de gestion des utilisateurs

```bash
ng generate service core/services/user.service
```

```typescript

import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';
import { User } from "../model/user.model";


@Injectable({
  providedIn: 'root'
})
export class UserService {
  private apiUrl = 'http://localhost:8000/api/users';
  constructor(private http: HttpClient) { }
  getUsers(): Observable<any> {
    return this.http.get<any>(this.apiUrl).pipe(
      catchError(error => {
        return throwError(error);
      })
    );
  }

  
  createUser(user: any): Observable<User> {
    return this.http.post<User>(this.apiUrl, user);
  }
  updateUser(id: number, user: any): Observable<User> {
    return this.http.put<User>(`${this.apiUrl}/${id}`, user);
  }
  deleteUser(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }
  assignRoles(userId: number, roles: string[]): Observable<User> {
    return this.http.post<User>(`${this.apiUrl}/${userId}/assign-roles`, { roles });
  }
}

```


Créons un module d'administration avec des composants pour gérer les utilisateurs :




Maintenant, implémentons le composant de liste des utilisateurs :

```typescript
// src/app/features/admin/users/user-list/user-list.component.ts
import { Component, OnInit } from '@angular/core';
import { UserService } from '../../../../core/services/user.service';
import { MessageService, ConfirmationService } from 'primeng/api';
import { User } from '../../../../core/models/user.model';

@Component({
  selector: 'app-user-list',
  templateUrl: './user-list.component.html',
  styleUrls: ['./user-list.component.scss']
})
export class UserListComponent implements OnInit {
  users: User[] = [];
  selectedUser: User = null;
  loading = false;
  displayUserDialog = false;
  dialogMode: 'new' | 'edit' = 'new';
  
  constructor(
    private userService: UserService,
    private messageService: MessageService,
    private confirmationService: ConfirmationService
  ) { }
  
  ngOnInit(): void {
    this.loadUsers();
  }
  
  loadUsers(): void {
    this.loading = true;
    this.userService.getUsers().subscribe({
      next: (data) => {
        this.users = data;
        this.loading = false;
      },
      error: (err) => {
        this.messageService.add({ 
          severity: 'error', 
          summary: 'Erreur', 
          detail: 'Impossible de charger les utilisateurs' 
        });
        this.loading = false;
      }
    });
  }
  
  openNew(): void {
    this.selectedUser = {} as User;
    this.dialogMode = 'new';
    this.displayUserDialog = true;
  }
  
  editUser(user: User): void {
    this.selectedUser = { ...user };
    this.dialogMode = 'edit';
    this.displayUserDialog = true;
  }
  
  deleteUser(user: User): void {
    this.confirmationService.confirm({
      message: `Êtes-vous sûr de vouloir supprimer l'utilisateur ${user.name} ?`,
      header: 'Confirmation',
      icon: 'pi pi-exclamation-triangle',
      accept: () => {
        this.loading = true;
        this.userService.deleteUser(user.id).subscribe({
          next: () => {
            this.users = this.users.filter(u => u.id !== user.id);
            this.messageService.add({ 
              severity: 'success', 
              summary: 'Succès', 
              detail: 'Utilisateur supprimé' 
            });
            this.loading = false;
          },
          error: (err) => {
            this.messageService.add({ 
              severity: 'error', 
              summary: 'Erreur', 
              detail: 'Impossible de supprimer l\'utilisateur' 
            });
            this.loading = false;
          }
        });
      }
    });
  }
  
  handleUserSave(user: User): void {
    if (this.dialogMode === 'new') {
      this.users.push(user);
    } else {
      const index = this.users.findIndex(u => u.id === user.id);
      this.users[index] = user;
    }
    this.users = [...this.users];
    this.displayUserDialog = false;
    this.selectedUser = null;
  }
  
  hideDialog(): void {
    this.displayUserDialog = false;
    this.selectedUser = null;
  }
  
  getRoleChip(role: string): any {
    const roleStyles = {
      'Admin': { severity: 'danger', icon: 'pi pi-shield' },
      'Manager': { severity: 'warning', icon: 'pi pi-star' },
      'User': { severity: 'info', icon: 'pi pi-user' }
    };
    
    return roleStyles[role] || { severity: 'secondary', icon: 'pi pi-tag' };
  }
}
```

```html
<!-- src/app/features/admin/users/user-list/user-list.component.html -->
<div class="card">
  <h2>Gestion des utilisateurs</h2>
  <p>Créez, modifiez et supprimez les utilisateurs du système</p>
  
  <p-toast></p-toast>
  <p-confirmDialog></p-confirmDialog>
  
  <p-toolbar styleClass="mb-4">
    <ng-template pTemplate="left">
      <div class="flex flex-wrap gap-2">
        <button pButton pRipple label="Nouvel utilisateur" icon="pi pi-plus" class="p-button-success" (click)="openNew()"></button>
      </div>
    </ng-template>
    <ng-template pTemplate="right">
      <button pButton pRipple label="Rafraîchir" icon="pi pi-refresh" class="p-button-outlined" (click)="loadUsers()"></button>
    </ng-template>
  </p-toolbar>
  
  <p-table 
    [value]="users" 
    [loading]="loading"
    [paginator]="true" 
    [rows]="10" 
    [rowsPerPageOptions]="[5, 10, 25]"
    responsiveLayout="scroll"
  >
    <ng-template pTemplate="header">
      <tr>
        <th pSortableColumn="id">ID <p-sortIcon field="id"></p-sortIcon></th>
        <th pSortableColumn="name">Nom <p-sortIcon field="name"></p-sortIcon></th>
        <th pSortableColumn="email">Email <p-sortIcon field="email"></p-sortIcon></th>
        <th>Rôles</th>
        <th style="width: 120px">Actions</th>
      </tr>
      <tr>
        <th>
          <input pInputText type="text" placeholder="Rechercher par ID" class="w-full">
        </th>
        <th>
          <input pInputText type="text" placeholder="Rechercher par nom" class="w-full">
        </th>
        <th>
          <input pInputText type="text" placeholder="Rechercher par email" class="w-full">
        </th>
        <th></th>
        <th></th>
      </tr>
    </ng-template>
    <ng-template pTemplate="body" let-user>
      <tr>
        <td>{{user.id}}</td>
        <td>{{user.name}}</td>
        <td>{{user.email}}</td>
        <td>
          <div *ngIf="user.roles?.length" class="flex flex-wrap gap-1">
            <p-tag 
              *ngFor="let role of user.roles" 
              [severity]="getRoleChip(role).severity"
              [icon]="getRoleChip(role).icon"
              [value]="role">
            </p-tag>
          </div>
          <span *ngIf="!user.roles?.length">Aucun rôle</span>
        </td>
        <td>
          <div class="flex flex-nowrap">
            <button pButton pRipple icon="pi pi-pencil" class="p-button-rounded p-button-text mr-2" 
                    pTooltip="Modifier" tooltipPosition="top" (click)="editUser(user)"></button>
            <button pButton pRipple icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" 
                    pTooltip="Supprimer" tooltipPosition="top" (click)="deleteUser(user)"></button>
          </div>
        </td>
      </tr>
    </ng-template>
    <ng-template pTemplate="emptymessage">
      <tr>
        <td colspan="5" class="text-center p-4">Aucun utilisateur trouvé.</td>
      </tr>
    </ng-template>
  </p-table>
</div>

<!-- Dialog pour ajouter/modifier un utilisateur -->
<app-user-form 
  *ngIf="selectedUser"
  [display]="displayUserDialog"
  [user]="selectedUser"
  [mode]="dialogMode"
  (save)="handleUserSave($event)"
  (cancel)="hideDialog()">
</app-user-form>
```

### Composant formulaire utilisateur

```typescript
// src/app/features/admin/users/user-form/user-form.component.ts
import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { UserService } from '../../../../core/services/user.service';
import { RoleService } from '../../../../core/services/role.service';
import { MessageService } from 'primeng/api';
import { User } from '../../../../core/models/user.model';
import { Role } from '../../../../core/models/role.model';

@Component({
  selector: 'app-user-form',
  templateUrl: './user-form.component.html',
  styleUrls: ['./user-form.component.scss']
})
export class UserFormComponent implements OnInit, OnChanges {
  @Input() display = false;
  @Input() user: User;
  @Input() mode: 'new' | 'edit' = 'new';
  @Output() save = new EventEmitter<User>();
  @Output() cancel = new EventEmitter<void>();
  
  userForm: FormGroup;
  roles: Role[] = [];
  submitting = false;
  
  constructor(
    private fb: FormBuilder,
    private userService: UserService,
    private roleService: RoleService,
    private messageService: MessageService
  ) { }
  
  ngOnInit(): void {
    this.initForm();
    this.loadRoles();
  }
  
  ngOnChanges(changes: SimpleChanges): void {
    if (changes.user && changes.user.currentValue) {
      this.initForm();
    }
  }
  
  private initForm(): void {
    this.userForm = this.fb.group({
      name: [this.user?.name || '', [Validators.required]],
      email: [this.user?.email || '', [Validators.required, Validators.email]],
      password: ['', this.mode === 'new' ? [Validators.required, Validators.minLength(8)] : []],
      roleIds: [this.user?.roles?.map(role => typeof role === 'string' ? role : role.id) || []]
    });
  }
  
  loadRoles(): void {
    this.roleService.getRoles().subscribe({
      next: (data) => {
        this.roles = data;
      },
      error: (err) => {
        this.messageService.add({ 
          severity: 'error', 
          summary: 'Erreur', 
          detail: 'Impossible de charger les rôles' 
        });
      }
    });
  }
  
  get f() { return this.userForm.controls; }
  
  onSubmit(): void {
    if (this.userForm.invalid) {
      Object.keys(this.userForm.controls).forEach(key => {
        const control = this.userForm.get(key);
        control.markAsTouched();
      });
      return;
    }
    
    this.submitting = true;
    const userData = { ...this.userForm.value };
    
    if (this.mode === 'edit') {
      this.userService.updateUser(this.user.id, userData).subscribe({
        next: (user) => {
          this.messageService.add({ 
            severity: 'success', 
            summary: 'Succès', 
            detail: 'Utilisateur mis à jour' 
          });
          this.submitting = false;
          this.save.emit(user);
        },
        error: (err) => {
          this.messageService.add({ 
            severity: 'error', 
            summary: 'Erreur', 
            detail: err.error?.message || 'Impossible de mettre à jour l\'utilisateur' 
          });
          this.submitting = false;
        }
      });
    } else {
      this.userService.createUser(userData).subscribe({
        next: (user) => {
          this.messageService.add({ 
            severity: 'success', 
            summary: 'Succès', 
            detail: 'Utilisateur créé' 
          });
          this.submitting = false;
          this.save.emit(user);
        },
        error: (err) => {
          this.messageService.add({ 
            severity: 'error', 
            summary: 'Erreur', 
            detail: err.error?.message || 'Impossible de créer l\'utilisateur' 
          });
          this.submitting = false;
        }
      });
    }
  }
  
  onCancel(): void {
    this.cancel.emit();
  }
}
```

```html
<!-- src/app/features/admin/users/user-form/user-form.component.html -->
<p-dialog 
  [(visible)]="display" 
  [modal]="true" 
  [style]="{width: '450px'}" 
  [header]="mode === 'new' ? 'Nouvel utilisateur' : 'Modifier utilisateur'"
  [draggable]="false" 
  [resizable]="false"
  (onHide)="onCancel()"
>
  <form [formGroup]="userForm" (ngSubmit)="onSubmit()">
    <div class="p-fluid">
      <div class="field">
        <label for="name">Nom</label>
        <input id="name" type="text" pInputText formControlName="name" />
        <small *ngIf="f.name.touched && f.name.errors?.required" class="p-error">
          Le nom est requis
        </small>
      </div>
      
      <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" pInputText formControlName="email" />
        <small *ngIf="f.email.touched && f.email.errors?.required" class="p-error">
          L'email est requis
        </small>
        <small *ngIf="f.email.touched && f.email.errors?.email" class="p-error">
          Format d'email invalide
        </small>
      </div>
      
      <div class="field">
        <label for="password">
          Mot de passe
          <span *ngIf="mode === 'edit'" class="text-xs text-500">(Laissez vide pour ne pas modifier)</span>
        </label>
        <p-password 
          id="password" 
          formControlName="password"
          [toggleMask]="true"
          [feedback]="true"
          styleClass="w-full"
          [promptLabel]="'Veuillez entrer un mot de passe'"
          [weakLabel]="'Faible'"
          [mediumLabel]="'Moyen'"
          [strongLabel]="'Fort'"
        ></p-password>
        <small *ngIf="f.password.touched && f.password.errors?.required" class="p-error">
          Le mot de passe est requis
        </small>
        <small *ngIf="f.password.touched && f.password.errors?.minlength" class="p-error">
          Le mot de passe doit contenir au moins 8 caractères
        </small>
      </div>
      
      <div class="field">
        <label for="roles">Rôles</label>
        <p-multiSelect 
          id="roles" 
          formControlName="roleIds"
          [options]="roles" 
          optionLabel="name" 
          optionValue="name"
          placeholder="Sélectionnez les rôles"
          display="chip"
        ></p-multiSelect>
      </div>
    </div>
    
    <div class="flex justify-content-end mt-4">
      <p-button 
        type="button" 
        label="Annuler" 
        icon="pi pi-times" 
        styleClass="p-button-text" 
        (click)="onCancel()"
      ></p-button>
      <p-button 
        type="submit" 
        label="Enregistrer" 
        icon="pi pi-check" 
        [loading]="submitting" 
        styleClass="ml-2"
      ></p-button>
    </div>
  </form>
</p-dialog>
```

## Résumé des bonnes pratiques
   backend**
   - Toujours valider toutes les entrées utilisateur
   - Utiliser des middleware pour les vérifications de rôles et permissions
   - Journaliser les événements d'authentification pour l'audit
   - Utiliser les codes de statut HTTP appropriés

2. **Sécurité frontend**
   - Ne jamais stocker des données sensibles dans le stockage client
   - Chiffrer toutes les données utilisateur stockées dans le navigateur
   - Mettre en œuvre une stratégie de rafraîchissement des tokens
   - Utiliser des gardes pour la protection des routes

3. **Organisation du code**
   - Garder la logique d'authentification dans des services dédiés
   - Utiliser des interfaces pour l'échange de données typées
   - Créer des directives réutilisables pour les vérifications de permissions
   - Séparer les préoccupations (auth, données, interface utilisateur)

4. **Conception de l'API**
   - Points de terminaison cohérents (/api/resource)
   - Utiliser les méthodes HTTP appropriées (GET, POST, PUT, DELETE)
   - Retourner des messages d'erreur significatifs
   - Inclure la pagination pour les grandes quantités de données

En suivant ces principes et implémentations, vous disposerez d'un système d'authentification robuste et sécurisé avec un contrôle d'accès granulaire dans votre application Laravel et Angular.
