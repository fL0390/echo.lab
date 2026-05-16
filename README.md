# echo
Plataforma de VMs para educación. VMs reales en el navegador, sin instalar nada.

El profesor crea una plantilla una vez, distribuye clones a cada alumno en segundos. El alumno abre el navegador y tiene su máquina Linux o Windows funcionando, sin VPN, sin clientes, sin configuración.

---
## ¿Por qué?

Gestionar VMs para un aula es lento y manual. Crear y entregar una máquina a cada alumno puede llevar horas, el acceso requiere clientes especiales, y cada VM ocupa su disco completo.

echo esta pensado para automatizar todo eso, el profesor configura el entorno una vez, y cada alumno recibe su propia máquina aislada en segundos, accesible desde cualquier navegador.

---
## Stack
PHP 8.3 · MySQL · Nginx · Proxmox VE · noVNC · Node.js

---
## Características
- Consola VM completa en el navegador vía noVNC + WebSocket
- Plantillas y clones enlazados — solo se almacenan los cambios de cada alumno
- Panel en tiempo real — estado, última actividad, parada forzada
- API REST con Bearer token para integración con plataformas externas

---
## API REST

```bash
curl -H "Authorization: Bearer tu-token" https://echo.lab/api/me.php
```

| Método | Endpoint                                  | Descripción           |
| ------ | ----------------------------------------- | --------------------- |
| GET    | `/api/me.php`                             | Usuario autenticado   |
| GET    | `/api/vms.php`                            | Lista de VMs          |
| POST   | `/api/vm_status.php`                      | Iniciar o parar VM    |
| POST   | `/api/courses.php?action=create_template` | Crear plantilla       |
| POST   | `/api/courses.php?action=assign_student`  | Asignar clon a alumno |
| POST   | `/api/courses.php?action=assign_course`   | Asignación            |

---

