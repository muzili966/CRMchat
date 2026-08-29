// Jenkinsfile — CRM-Chat 客服系统（TP6 + Swoole 单体应用，应用目录 crmchat/）
//
// 参考 jetlinks-api 流水线模式编写：multibranch + Harbor 推送 + 部署机 compose 拉起。
// PHP 项目无编译产物，构建机不需要装 PHP：语法检查与单元测试在 php:7.4-cli 容器内执行。
//
// 前置条件：
//   1. 构建节点需有 Docker（当前 Jenkins 只有内置节点且无标签，故用 agent any）
//   2. Credentials: harbor-crmchat（Harbor crmchat 项目的 robot 账号）
//   3. Jenkins 与部署为同一台服务器：部署阶段在本机直接执行 compose，无需SSH；
//      未来拆分独立部署机时，将部署阶段改回 withCredentials(sshUserPrivateKey)+scp/ssh 远程执行
//   4. 部署定义（compose 与 .env.<env>）以仓库 crmchat/deploy/compose/ 为唯一事实源，
//      部署阶段自动同步到部署目录；仅 prod 的 .env.prod 不入库，需在服务器放置一次
//   5. 首次部署后需初始化数据库（访问 /install 向导或导入 crmeb.sql）

def SERVICE_NAME  = 'crm-chat'
def REGISTRY      = '10.242.98.181:9093/crmchat'
// 登录只需主机部分: 10.242.98.181:9093/crmchat → 10.242.98.181:9093
def REGISTRY_HOST = '10.242.98.181:9093'
def DEPLOY_DIR    = '/opt/crm-chat/compose'
// 各环境宿主机映射端口（容器内固定 20108）
def APP_PORT      = [dev: '20118', test: '20128']

pipeline {
    agent any

    options {
        quietPeriod(10)
        disableConcurrentBuilds(abortPrevious: true)
        timeout(time: 30, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '30'))
        timestamps()
    }

    parameters {
        choice(name: 'ENV', choices: ['dev', 'test', 'staging', 'prod'], description: '部署目标环境')
        string(name: 'TAG', defaultValue: '', description: '镜像 Tag，留空则用 git short hash')
        booleanParam(name: 'SKIP_TESTS', defaultValue: false, description: '跳过单元测试（仅紧急发布使用）')
    }

    stages {
        stage('检出') {
            steps {
                script { env.TASK_START_TIME_MILLIS = "${System.currentTimeMillis()}" }
                checkout scm
            }
        }

        stage('语法检查 & 单元测试') {
            when { expression { !params.SKIP_TESTS } }
            steps {
                // phpunit.phar 用 php copy() 下载，不依赖容器内 curl；
                // vendor 已随仓库提交，无需 composer install
                sh '''
                    docker run --rm -v "$WORKSPACE/crmchat":/app -w /app php:7.4-cli sh -c "
                        find app crmeb config route -name '*.php' -print0 | xargs -0 -n1 -P4 php -l > /dev/null &&
                        php -r \\"copy('https://phar.phpunit.de/phpunit-9.6.phar','/tmp/phpunit.phar');\\" &&
                        php /tmp/phpunit.phar -c phpunit.xml
                    "
                '''
            }
        }

        stage('Docker 构建 & 推送') {
            steps {
                script {
                    def tag = params.TAG?.trim() ?: sh(
                        script: 'git rev-parse --short HEAD', returnStdout: true).trim()
                    env.IMAGE_TAG = tag
                    env.IMAGE = "${REGISTRY}/${SERVICE_NAME}:${tag}"

                    // Dockerfile 位于 crmchat/，构建上下文同目录
                    dir('crmchat') {
                        sh """
                            export DOCKER_BUILDKIT=1
                            export DOCKER_MAX_CONCURRENT_UPLOADS=2
                            docker build -t ${env.IMAGE} -t ${REGISTRY}/${SERVICE_NAME}:latest .
                        """
                        // 显式登录：不依赖构建机手工 docker login 的会话（会过期，过期后一律 401）
                        withCredentials([usernamePassword(
                                credentialsId: 'harbor-crmchat',
                                usernameVariable: 'HARBOR_USER',
                                passwordVariable: 'HARBOR_PASS')]) {
                            sh """
                                echo "\$HARBOR_PASS" | docker login ${REGISTRY_HOST} -u "\$HARBOR_USER" --password-stdin
                                docker push ${env.IMAGE}
                                docker push ${REGISTRY}/${SERVICE_NAME}:latest
                            """
                        }
                    }
                }
            }
        }

        stage('部署') {
            when { expression { params.ENV in ['dev', 'test'] } }
            steps {
                // Jenkins与部署同机：本机直接同步compose目录并拉起，镜像用刚构建的本地镜像无需pull
                // 仓库为部署事实源（不会覆盖服务器上未入库的 .env.prod）
                // mysql/redis 与应用同编排，故不加 --no-deps；-p 按环境隔离项目与数据卷
                sh """
                    mkdir -p ${DEPLOY_DIR}
                    cp -r crmchat/deploy/compose/. ${DEPLOY_DIR}/
                    cd ${DEPLOY_DIR}
                    REGISTRY=${REGISTRY} TAG=${env.IMAGE_TAG} \\
                      docker compose -p ${SERVICE_NAME}-${params.ENV} \\
                                     -f docker-compose.yaml \\
                                     --env-file .env.${params.ENV} \\
                                     up -d
                """
                echo "已部署: ${SERVICE_NAME} → ${params.ENV} (${env.IMAGE})"
            }
        }

        stage('部署验证') {
            when { expression { params.ENV in ['dev', 'test'] } }
            steps {
                // 同机部署直接探活本机端口；数据库未初始化时返回400/404也视为进程存活
                retry(10) {
                    sleep 10
                    sh "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:${APP_PORT[params.ENV]}/api/admin/login/info | grep -E '200|400|404'"
                }
            }
        }
    }

    post {
        always { cleanWs() }
        failure {
            script {
                def taskStartMillis = (env.TASK_START_TIME_MILLIS ?: '0') as long
                def base = taskStartMillis > 0 ? taskStartMillis : currentBuild.startTimeInMillis
                def totalSec = ((System.currentTimeMillis() - base) / 1000).longValue()
                def durationStr = "${(totalSec / 60).intValue()}分${(totalSec % 60).intValue()}秒"
                echo "构建失败: ${SERVICE_NAME} [${params.ENV}] 分支=${env.BRANCH_NAME} 耗时=${durationStr}"
            }
        }
    }
}
