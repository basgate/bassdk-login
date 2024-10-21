//Description on url
// https://www.ahmetkucukoglu.com/en/how-to-publish-asp-net-core-application-by-using-jenkins

String githubUrl = "https://github.com/ansiabdo/bassdk-wp-login.git"
String projectName = "bassdk-wp-login"
String userName = "basgate-_979mf9lyuyh"
String iisApplicationPath = "C:\\inetpub\\vhosts\\basgate-sandbox.com\\wp-plugin.basgate-sandbox.com\\wp-content\\plugins\\bassdk-wp-login"

node () {
    stage('Clean Workspace'){
      // cleanWs()
      properties([
        disableConcurrentBuilds(abortPrevious: true)])
    }
    stage('Checkout') {
        checkout([
            $class: 'GitSCM',
            branches: [[name: 'main']],
            doGenerateSubmoduleConfigurations: false,
            extensions: [],
            submoduleCfg: [],
            userRemoteConfigs: [[credentialsId: 'User-Token',url: """ "${githubUrl}" """]]])
    }
    stage('Deploy'){
      dir("""${WORKSPACE}""") {
            bat """
            xcopy * "${iisApplicationPath}" /Y /E
            """
      }
    }
    stage('Delete .hidden files'){
      dir("""${WORKSPACE}""") {
            bat """
            del "${iisApplicationPath}"\\.gitignore
            """
      }
    }
}

