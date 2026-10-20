# NutriTrace on Kubernetes (Minikube + Argo CD)

```
git push ──► CI (tests, SonarCloud, image build + Trivy) ──► ghcr.io/ademchebbi77/nutritrace-app:main-<sha>
                                   │ green
                                   ▼
             CD (.github/workflows/cd.yml) writes main-<sha> in k8s/overlays/minikube and commits
                                   │
                                   ▼
             Argo CD (in the cluster) sees the commit ──► migrations Job ──► rolling update of the app
```

| Folder | Content |
|---|---|
| `base/` | Namespace, ConfigMap, MySQL (StatefulSet), uploads volume, migrations Job, app (Deployment + Service + HPA 2→4 pods), scheduler, Ingress |
| `overlays/minikube/` | The deployed image tag (changed by the CD workflow only) |
| `argocd/` | Argo CD install (pinned version) and the `nutritrace` Application |
| `secrets.example.env` | Template of the Secret (APP_KEY, passwords). The real `secrets.env` is never committed |

The local AI service is **not** in the cluster: it needs the GPU. Start `NutriTrace/ai/start.bat` on the PC;
the pods reach it at `host.minikube.internal:8001`.

## First installation

Requirements: Docker Desktop, [Minikube](https://minikube.sigs.k8s.io/docs/start/), kubectl.

```bash
# 1. Cluster (profile "nutritrace") with the ingress controller and the metrics for the HPA
minikube start -p nutritrace --driver=docker --cpus=4 --memory=4096 --addons=ingress,metrics-server

# 2. Secret: copy the template, fill it in (APP_KEY: php artisan key:generate --show in NutriTrace/)
cp k8s/secrets.example.env k8s/secrets.env
kubectl apply -f k8s/base/namespace.yaml
kubectl create secret generic nutritrace-secrets -n nutritrace --from-env-file=k8s/secrets.env

# 3. Argo CD, then the application: Argo CD deploys everything from the repository
kubectl apply -k k8s/argocd --server-side --force-conflicts
kubectl wait -n argocd --for=condition=available deploy --all --timeout=600s
kubectl apply -f k8s/argocd/application.yaml

# 4. Demo data (once the app pods are ready)
kubectl exec -n nutritrace deploy/app -- php artisan db:seed --force
```

Without Argo CD, `kubectl apply -k k8s/overlays/minikube` deploys the same thing by hand.

## Open the site

Port 80 is often taken on Windows, so the ingress is opened on 8088:

```bash
kubectl port-forward -n ingress-nginx svc/ingress-nginx-controller 8088:80
```

➡️ <http://nutritrace.127.0.0.1.nip.io:8088> (nip.io is a public DNS name pointing to 127.0.0.1: no hosts file to edit).
Demo accounts: password `password`, e.g. `admin@nutritrace.test`.

## Argo CD dashboard

```bash
kubectl port-forward -n argocd svc/argocd-server 8443:443
# user "admin", initial password:
kubectl get secret -n argocd argocd-initial-admin-secret -o jsonpath="{.data.password}" | base64 -d
```

➡️ <https://localhost:8443> (self-signed certificate: accept the warning). Argo CD checks the repository
every 3 minutes; "Refresh" checks at once.

## Useful commands

```bash
kubectl get pods,hpa,pvc -n nutritrace                     # state of the deployment
kubectl logs -n nutritrace deploy/app -f                    # Laravel and Apache logs
kubectl logs -n nutritrace job/nutritrace-migrate           # last migrations
kubectl rollout history -n nutritrace deploy/app            # deployed versions
minikube -p nutritrace stop / start                         # pause / resume the cluster (data is kept)
```

**Rollback**: revert the "Deploy main-… to Kubernetes" commit on main (or set an older `newTag`):
Argo CD rolls back to that image. A change made by hand in the cluster is undone by Argo CD (selfHeal):
every change goes through git.

**After a CD deployment**, the bot has pushed a commit to main: run `git pull` before your next push.
