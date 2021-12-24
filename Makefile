ifndef VERBOSE
.SILENT:
endif

include Makefile.settings

EXT_NAME         ?= ext-name-not-set

# If this is being run on Github, then we're in a container and
# GITHUB_ACTIONS is true.
ifeq ("${GITHUB_ACTIONS}","true")
IN_CONTAINER := true
endif

# Ditto for Gitlab, except we'll check for GITLAB_CI.
ifeq ("${GITLAB_CI}","true")
IN_CONTAINER := true
endif

# If we're in a container then use the targets in the
# Makefile.inContainer file
ifeq ("${IN_CONTAINER}","true")
include Makefile.inContainer

else
# We are not in a container (or, at least, IN_CONTAINER is not
# set to true).  In this case, since we want the other targets to
# only be used in a container, we'll set up the container and
# call ourself in the container.

# Since MAKECMDGOALS contains whatever the first target is, we
# make the first target from the command line our default target
# (but only if we aren't already calling "inContainer").

ifeq ("$(filter $(firstword ${MAKECMDGOALS}),inContainer startSite stopSite run)","")
.PHONY: $(firstword ${MAKECMDGOALS})
$(firstword ${MAKECMDGOALS}):
	${MAKE} inContainer goals="${MAKECMDGOALS}"
endif

.SILENT: startSite
startSite: verifyExtName ${lsPath}
	test "`podman ps -f name=${siteName} -q`" = ""											||	(	\
		echo "A container with the name '${siteName}' is already running.  Please destroy it"	&&	\
		echo "or specify a different siteName."													&&	\
		exit 10																						\
	)
	${dockerCli} run -d --rm -w /target $(call mountAll,${mounts}) $(call envFileVars,${copyVars})	\
		--name ${siteName} -p 80:80 ${containerID}

.SILENT: stopSite
stopSite: verifyExtName
	test "`podman ps -f name=${siteName} -q`" != ""											||	(	\
		echo "No container with the name ${siteName} is running.  Nothing to do."				&&	\
		exit 10																						\
	)
	${dockerCli} rm -f ${siteName}

.PHONY: inContainer
inContainer: ${lsPath} ${mwVendor}
	${dockerCli} run --rm -w /target $(call mountAll,${mounts}) $(call envFileVars,${copyVars})		\
		${containerID}																				\
		${MAKE} -f Makefile.inContainer setupLinks ${goals}

${lsPath}: ${mwVendor}
	mkdir -p ${ciDataPath}
	chmod 1777 ${ciDataPath}
	cid=`${dockerCli} create $(call mountAll,${mounts}) $(call envFileVars,${copyVars})				\
		${containerID}`																			&&	\
	${dockerCli} start $$cid																	&&	\
	${dockerCli} exec -w /target $$cid																\
			${MAKE} -f Makefile.inContainer															\
				getComposer 																		\
				mediaWikiComposerUpdate																\
				setupLinks																			\
				mediaWikiInstall																	\
				enableDebugOutput																	\
				installSemanticMediaWiki														&&	\
	${dockerCli} cp $$cid:/var/www/html/LocalSettings.php $@									&&	\
	${dockerCli} rm -f $$cid
	touch $@

${mwVendor}:
	mkdir -p ${ciPath}
	cid=`${dockerCli} create ${containerID}`													&&	\
	${dockerCli} cp "$$cid:${MW_INSTALL_PATH}/vendor"												\
		${mwVendor}																				&&	\
	${dockerCli} rm -f $$cid
	touch $@

${extJsonJson}:
	test -d ${mirrorPath}																		||	\
		mkdir -p ${mirrorPath}
	older=$(shell find $@ -mmin +120 | wc -l)													&&	\
	test $$older -ne 1																		||	(	\
		cd ${mirrorPath}																		&&	\
		wget --mirror ${extJsonJsonSrc}																\
	)

endif

# Local Variables:
# eval: (display-fill-column-indicator-mode)
# eval: (set (make-local-variable 'fill-column) 101)
# End:
