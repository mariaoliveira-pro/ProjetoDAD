# Tools

These are the tools we will need to deploy our projet to production:

- `kubectl`: the [Kubernetes CLI](https://kubernetes.io/docs/tasks/tools/#kubectl) we need to define our project state and run commands against the cluster.
- `docker`: the [container management tool](https://www.docker.com/products/docker-desktop/) that we will use to create the container images (locally) and push them to the container registry

There are some other tools we might find usefull, but are not mandatory, such as:

- [Lens](https://k8slens.dev/products/lens-k8s-ide): the UI tool to connect to our Kubernetes cluster. This can do most of the tasks we will do with `kubectl` but in a UI application
- [just](https://github.com/casey/just): a simpler Makefile, used to run commands and in our case has the added benefit of documenting the commands in our repository

## Installation - Windows

For `kubectl` follow the instructions in the [official documentation page](https://kubernetes.io/docs/tasks/tools/install-kubectl-windows/). We recommend using [Chocolatey](https://chocolatey.org/) by running:

```powershell
choco install kubernetes-cli
```

For docker, there are a few steps we should take to have the best change at getting this right the first time, one critical one is to choose the virtualization environment: WLS (version 2) or Hyper-V. The [docker documentation](https://docs.docker.com/desktop/setup/install/windows-install/) has a very detailed explanation of the options.

## Installation - Linux

For `kubectl` don't use your distribution's package manager, unless you are confortable with checking and managing package versions. The version of the CLI must match the version of our cluster. The simplest way to achieve this is to install the tool manually with the following commands:

```bash
curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl.sha256"

sudo install -o root -g root -m 0755 kubectl /usr/local/bin/kubectl
```

Check the [official documentation](https://kubernetes.io/docs/tasks/tools/install-kubectl-linux) for more details.

For docker, the [official documentation](https://docs.docker.com/desktop/setup/install/linux/) has instructions for several of the major distributions. It's important to follow these to make sure we have the latest repositories added to our package management system.

## Installation - MacOs

For `kubectl` follow the instructions in the [official documentation page](http://kubernetes.io/docs/tasks/tools/install-kubectl-macos/#install-with-homebrew-on-macos). We recommend using [Homebrew](https://brew.sh/) by running:

```powershell
brew install kubernetes-cli
```

For docker, the [official documentation](https://docs.docker.com/desktop/setup/install/mac-install/) points to simple installers (depending on our hardware platform, Intel or Apple silicon)

## Checking the tools

After the installation we can check our tools by running:

```
kubectl version --client

docker --version
```
