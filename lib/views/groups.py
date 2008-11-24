from dispatch.view import View
from template.shortcuts import evaluate_main
from dispatch.response import Response, XMLStatusResponse, XMLTemplateResponse
from views.error import ErrorResponse
from models.user import User
from models.group import Group, addGroup
from config.authz.authz import GroupExistsError, UnknownGroupError
from auth.decorators import *

class Groups(View):
	@login_required
	def handler(self, req, path):
		localvars = {}
		config = Config()

		if req.is_ajax():
			return self.ajaxhandler(req, path)

		if len(path) < 1:
			return ErrorResponse('Invalid path', request=req)

		if len(path) > 0:
			localvars['selected_type'] = 'groups'
		if len(path) > 1:
			localvars['selected_object'] = path[1]

		try:
			if path[0] == 'show':
				return self.show(req, path[1:], localvars)
			if path[0] == 'add':
				return self.add(req, path[1:], localvars)
		except Unauthorized:
			return Redirect(config.base_url)

		return ErrorResponse('Unknown path', request=req)

	@admin_required
	def show(self, req, path, localvars):
		if len(path) < 1:
			return ErrorResponse('Invalid path', request=req)

		is_admin = req.session['user'].is_admin
		try:
			group = Group(path[0])
		except (IndexError, UnknownGroupError):
			if not is_admin:
				return ErrorResponse('Not permitted', request=req)

			return ErrorResponse('This group does not exist.', request=req)

		if not is_admin and req.session['user'].name not in group.members:
			return ErrorResponse('Not permitted', request=req)

		localvars['group'] = group
		formatted = evaluate_main('groups.html', localvars, request=req)
		return Response(formatted)

	def showAddForm(self, req, groupname, errormsg=''):
		localvars = {}
		localvars['errormsg'] = errormsg
		localvars['groupname'] = groupname
		formatted = evaluate_main('newgroup.html', localvars, request=req)
		return Response(formatted)

	@admin_required
	def add(self, req, path, localvars):
		config = Config()
		base_url = config.base_url
		groupname = ''

		if req.post and req.post['groupname']:
			import re

			groupname = req.post['groupname'].value.strip()
			if re.findall('[^a-zA-Z0-9_-]', groupname):
				return self.showAddForm(req, groupname, 'Invalid characters in groupname')
			if groupname == '':
				return self.showAddForm(req, groupname, 'Groupname not supplied')

			url = base_url + '/groups/show/' + groupname

			try:
				addGroup(groupname)
			except IOError:
				return ErrorResponse('File permission denied', request=req)
			except GroupExistsError:
				return self.showAddForm(req, groupname, 'Group %s already exists' % groupname)

			return Redirect(url)

		return self.showAddForm(req, groupname)

	def ajaxhandler(self, req, path):
		success = False
		error = ''
		response = None
		username = ''

		if len(path) < 2:
			return XMLStatusResponse('', False, 'Invalid Path')

		action = path[0]
		groupname = path[1]

		if action == 'delete':
			return self.removeGroup(groupname)

		if 'removeMember' in req.post:
			return self.removeMember(req, groupname)

		if 'addMember' in req.post:
			return self.addMember(req, groupname)

		if 'listGroupUsers' in req.post:
			return self.listGroupUsers(req, Group(groupname))

		return XMLStatusResponse('', False, 'Unknown command')

	def listGroupUsers(self, req, group):
		if req.session['user'].is_admin:
			return XMLTemplateResponse("ajax/groupmembers.xml",
					{"members": group.members, "nonmembers": group.nonmembers,
						"group": group.name})

		if group.name not in req.session['user'].member_of:
			return XMLStatusResponse('listGroupUsers', False, "You do not have permission to"
					"view this group.")

		return XMLTemplateResponse("ajax/groupmembers.xml",
				{"members": group.members, "nonmembers": [],
					"group": group.name})

	@admin_required
	def removeMember(self, req, groupname):
		group = Group(groupname)
		username = req.post['removeMember'].value
		# TODO: Make this a setting in submin.conf?
		if group.name == "submin-admins" and username == req.session['user'].name:
			return XMLStatusResponse('removeMember', False,
					"You cannot remove yourself from %s" % group.name)

		success = Group(groupname).removeMember(username)
		msgs = {True: 'User %s removed from group %s' % (username, groupname),
				False: 'User %s is not a member of group %s' % (username, groupname)}
		return XMLStatusResponse('removeMember', success, msgs[success])

	@admin_required
	def addMember(self, req, groupname):
		username = req.post['addMember'].value
		success = Group(groupname).addMember(username)
		msgs = {True: 'User %s added to group %s' % (username, groupname),
				False: 'User %s already in group %s' % (username, groupname)}
		return XMLStatusResponse('addMember', success, msgs[success])

	@admin_required
	def removeGroup(self, groupname):
		if groupname == 'submin-admins':
			return XMLStatusResponse('removeGroup', False,
				'You are not allowed to delete the submin-admins group')

		try:
			group = Group(groupname)
			group.remove()
		except IOError:
			return XMLStatusResponse('removeGroup', False, 'File permisson denied')
		except UnknownGroupError:
			return XMLStatusResponse('removeGroup', False,
				'Group %s does not exist' % groupname)

		return XMLStatusResponse('removeGroup', True, 'Group %s deleted' % group)

