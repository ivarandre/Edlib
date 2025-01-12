{{- define "helpers.deployment" }}
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ .name }}
  labels:
    app: {{ .name }}
spec:
  replicas: {{ .replicaCount }}
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 2
      maxUnavailable: 0
  selector:
    matchLabels:
      app: {{ .name }}
  template:
    metadata:
      labels:
        app: {{ .name }}
{{ if .fluentdParser }}
        fluentd-parser: {{ .fluentdParser }}
{{ end }}
    spec:
      affinity:
        podAntiAffinity:
          preferredDuringSchedulingIgnoredDuringExecution:
            - weight: 1
              podAffinityTerm:
                labelSelector:
                  matchExpressions:
                    - key: app
                      operator: In
                      values:
                        - {{ .name }}
                topologyKey: 'kubernetes.io/hostname'
{{ if .initImage }}
      initContainers:
{{ include "helpers.container" (dict "name" "init" "image" .initImage "tag" .imageTag "envFromConfig" .envFromConfig "envFromSecret" .envFromSecret ) | indent 8 }}
{{ end }}
      containers:
{{ include "helpers.container" (dict "name" .name "image" .image "tag" .imageTag "resources" .resources "envFromConfig" .envFromConfig "envFromSecret" .envFromSecret "healthUrl" .healthUrl "port" (.port | default "80") ) | indent 8 }}
      imagePullSecrets:
        - name: dockerconfigjson-github-com
{{- end }}
